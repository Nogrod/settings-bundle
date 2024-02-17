<?php
/*
 * This file is part of jbtronics/settings-bundle (https://github.com/jbtronics/settings-bundle).
 *
 * Copyright (c) 2024 Jan Böhmer
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */

declare(strict_types=1);


namespace Jbtronics\SettingsBundle\Storage\ORM;


use Doctrine\ORM\EntityManagerInterface;
use Jbtronics\SettingsBundle\Storage\StorageAdapterInterface;

class ORMStorageAdapter implements StorageAdapterInterface
{

    /** @var array[][] */
    private array $cache = [];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ?string $defaultEntityClass = null,
        private readonly bool $fetchAll = false,
    )
    {
        if ($this->defaultEntityClass !== null && !is_subclass_of($this->defaultEntityClass, AbstractORMStorageAdapterEntity::class)) {
            throw new \InvalidArgumentException('The default entity class must be a subclass of ' . AbstractORMStorageAdapterEntity::class);
        }
    }

    /**
     * Returns the entity object for the given key. If the entity does not exist, it will be created.
     * @param  string  $key
     * @param  string  $entityClass The class of the entity to use
     * @return AbstractORMStorageAdapterEntity
     */
    private function getEntityObject(string $key, string $entityClass): AbstractORMStorageAdapterEntity
    {
        if (!is_subclass_of($entityClass, AbstractORMStorageAdapterEntity::class)) {
            throw new \InvalidArgumentException('The entity class must be a subclass of ' . AbstractORMStorageAdapterEntity::class);
        }

        //Check if we already have the entity in the cache
        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }

        //Retrieve the entity from the database or create a new one if it does not exist
        $entity = $this->entityManager->getRepository($entityClass)->findOneBy(['key' => $key]);
        if ($entity === null) {
            $entity = new $entityClass();
            $entity->setKey($key);
        }

        //Store the entity in the cache
        $this->cache[$key] = $entity;

        return $entity;
    }

    /**
     * This function preloads all entity objects of the given class into the cache, so that consecutive calls to getEntityObject() do not require a database query.
     * @param  string  $entityClass
     * @return void
     */
    private function preloadAllEntityObjects(string $entityClass): void
    {
        //If the cache is already filled, we do not need to preload the entities
        if (!empty($this->cache)) {
            return;
        }

        if (!is_subclass_of($entityClass, AbstractORMStorageAdapterEntity::class)) {
            throw new \InvalidArgumentException('The entity class must be a subclass of ' . AbstractORMStorageAdapterEntity::class);
        }

        $entities = $this->entityManager->getRepository($entityClass)->findAll();
        foreach ($entities as $entity) {
            $this->cache[$entity->getKey()] = $entity;
        }
    }

    public function save(string $key, array $data, array $options = []): void
    {
        //Retrieve the entity object
        $entity = $this->getEntityObject($key, $options['entity_class'] ?? $this->defaultEntityClass);

        //Set the data
        $entity->setData($data);

        //Persist the entity (if not already done)
        $this->entityManager->persist($entity);

        //And save the changes
        $this->entityManager->flush();
    }

    public function load(string $key, array $options = []): ?array
    {
        $entityClass = $options['entity_class'] ?? $this->defaultEntityClass;

        //Preload all entity objects if the fetchAll option is set
        if ($this->fetchAll) {
            $this->preloadAllEntityObjects($entityClass);
        }

        //Retrieve the entity object
        $entity = $this->getEntityObject($key, $options['entity_class'] ?? $this->defaultEntityClass);

        //Return the data
        return $entity->getData();
    }
}