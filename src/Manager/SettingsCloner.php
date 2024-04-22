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


namespace Jbtronics\SettingsBundle\Manager;

use Jbtronics\SettingsBundle\Helper\PropertyAccessHelper;
use Jbtronics\SettingsBundle\Metadata\MetadataManager;
use Jbtronics\SettingsBundle\Metadata\ParameterMetadata;
use Jbtronics\SettingsBundle\Proxy\ProxyFactoryInterface;
use Jbtronics\SettingsBundle\Proxy\SettingsProxyInterface;
use Jbtronics\SettingsBundle\Settings\CloneAndMergeAwareSettingsInterface;
use Symfony\Component\VarExporter\LazyObjectInterface;

class SettingsCloner implements SettingsClonerInterface
{
    public function __construct(
        private readonly MetadataManager $metadataManager,
        private readonly ProxyFactoryInterface $proxyFactory,
    )
    {
    }

    public function createClone(object $settings): object
    {
        $embedded_clones = [];
        return $this->createCloneInternal($settings, $embedded_clones);
    }

    private function createCloneInternal(object $settings, array &$embeddedClones): object
    {
        $metadata = $this->metadataManager->getSettingsMetadata($settings);

        //Use reflection to create a new instance of the settings class
        $reflClass = new \ReflectionClass($metadata->getClassName());
        $clone = $reflClass->newInstanceWithoutConstructor();

        //Iterate over all properties and copy them to the new instance
        foreach ($metadata->getParameters() as $parameter) {
            $oldVar = PropertyAccessHelper::getProperty($settings, $parameter->getPropertyName());

            //If the property is an object, we need to clone it, to get a new instance
            if ($this->shouldBeCloned($oldVar, $parameter)) {
                $newVar = clone $oldVar;
            } else {
                $newVar = $oldVar;
            }

            //Set the property on the new instance
            PropertyAccessHelper::setProperty($clone, $parameter->getPropertyName(), $newVar);
        }

        //Add the clone to the list of embedded clones, so that we can access it in other iterations of this method
        $embeddedClones[$metadata->getClassName()] = $clone;

        //Iterate over all embedded settings
        foreach ($metadata->getEmbeddedSettings() as $embeddedSetting) {
            //If the embedded setting was already cloned, we can reuse it
            if (isset($embeddedClones[$embeddedSetting->getTargetClass()])) {
                $embeddedClone = $embeddedClones[$embeddedSetting->getTargetClass()];
            } else {
                //Otherwise, we need to create a new clone, which we lazy load, via our proxy system
                $embeddedClone = $this->proxyFactory->createProxy($embeddedSetting->getTargetClass(), function () use ($embeddedSetting, $settings, $embeddedClones) {
                    return $this->createCloneInternal(PropertyAccessHelper::getProperty($settings, $embeddedSetting->getPropertyName()), $embeddedClones);
                });
            }

            //Set the embedded clone on the new instance
            PropertyAccessHelper::setProperty($clone, $embeddedSetting->getPropertyName(), $embeddedClone);
        }

        //If the settings class implements the CloneAndMergeAwareSettingsInterface, call the afterClone method
        if ($clone instanceof CloneAndMergeAwareSettingsInterface) {
            $clone->afterSettingsClone($settings);
        }

        return $clone;
    }

    public function mergeCopyInternal(object $copy, object $into, bool $recursive, array &$mergedClasses): object
    {
        $metadata = $this->metadataManager->getSettingsMetadata($copy);

        //Iterate over all properties and copy them to the new instance
        foreach ($metadata->getParameters() as $parameter) {
            $oldVar = PropertyAccessHelper::getProperty($copy, $parameter->getPropertyName());

            //If the property is an object, we need to clone it, to get a new instance
            if ($this->shouldBeCloned($oldVar, $parameter)) {
                $newVar = clone $oldVar;
            } else {
                $newVar = $oldVar;
            }

            //Set the property on the new instance
            PropertyAccessHelper::setProperty($into, $parameter->getPropertyName(), $newVar);
        }

        $mergedClasses[$metadata->getClassName()] = $into;

        //If recursive mode is active, also merge the embedded settings
        if ($recursive) {
            foreach ($metadata->getEmbeddedSettings() as $embeddedSetting) {
                //Skip if the class was already merged
                if (isset($mergedClasses[$embeddedSetting->getTargetClass()])) {
                    continue;
                }

                $copyEmbedded = PropertyAccessHelper::getProperty($copy, $embeddedSetting->getPropertyName());

                //If the embedded setting is a lazy proxy and it was not yet initialized, we can skip it as the data was not modified
                if ($copyEmbedded instanceof SettingsProxyInterface && $copyEmbedded instanceof LazyObjectInterface && !$copyEmbedded->isLazyObjectInitialized()) {
                    continue;
                }

                $intoEmbedded = PropertyAccessHelper::getProperty($into, $embeddedSetting->getPropertyName());

                //Recursively merge the embedded setting
                $this->mergeCopyInternal($copyEmbedded, $intoEmbedded, $recursive, $mergedClasses);
            }
        }

        //If the settings class implements the CloneAndMergeAwareSettingsInterface, call the afterMerge method
        if ($into instanceof CloneAndMergeAwareSettingsInterface) {
            $into->afterSettingsMerge($copy);
        }

        return $into;
    }

    public function mergeCopy(object $copy, object $into, bool $recursive = true): object
    {
        $mergedClasses = [];
        return $this->mergeCopyInternal($copy, $into, $recursive, $mergedClasses);
    }

    /**
     * Checks if the given value should be cloned or not
     * @param  mixed  $value
     * @param  ParameterMetadata  $parameterMetadata
     * @return bool
     */
    private function shouldBeCloned(mixed $value, ParameterMetadata $parameterMetadata): bool
    {
        if (!is_object($value)) {
            return false;
        }

        //We can not clone enums
        if ($value instanceof \UnitEnum) {
            return false;
        }

        //Otherwise use the cloneable flag from the parameter metadata
        return $parameterMetadata->isCloneable();
    }
}