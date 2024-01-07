<?php


/*
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

namespace Jbtronics\SettingsBundle\ParameterTypes;

use Symfony\Component\DependencyInjection\ServiceLocator;

final class ParameterTypeRegistry implements ParameterTypeRegistryInterface
{

    public function __construct(
        private readonly ServiceLocator $locator
    )
    {
    }

    /**
     * Return an array of all registered parameter types.
     * in the format ['parameter_type_service_name' => 'parameter_type_service_class']
     * @return array
     * @phpstan-return array<string, class-string<ParameterTypeInterface>>
     */
    public function getRegisteredParameterTypes(): array
    {
        return $this->locator->getProvidedServices();
    }

    public function getParameterType(string $className): ParameterTypeInterface
    {
        return $this->locator->get($className);
    }
}