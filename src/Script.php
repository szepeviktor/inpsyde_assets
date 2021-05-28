<?php

/*
 * This file is part of the Assets package.
 *
 * (c) Inpsyde GmbH
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Inpsyde\Assets;

use Inpsyde\Assets\Handler\AssetHandler;
use Inpsyde\Assets\Handler\ScriptHandler;

class Script extends BaseAsset implements Asset
{
    /**
     * @var array<string, mixed>
     */
    protected $localize = [];

    /**
     * @var array{after:string[], before:string[]}
     */
    protected $inlineScripts = [
        'after' => [],
        'before' => [],
    ];

    /**
     * @var bool
     */
    protected $inFooter = true;

    /**
     * @var array{domain:string, path:string|null}
     */
    protected $translation = [
        'domain' => '',
        'path' => null,
    ];

    /**
     * @var bool
     */
    protected $useDependencyExtractionPlugin = false;

    /**
     * @var bool
     */
    protected $resolvedDependencyExtractionPlugin = false;

    /**
     * @return array<string, mixed>
     */
    public function localize(): array
    {
        $output = [];
        foreach ($this->localize as $objectName => $data) {
            $output[$objectName] = is_callable($data)
                ? $data()
                : $data;
        }

        return $output;
    }

    /**
     * @param string $objectName
     * @param string|int|array|callable $data
     *
     * @return static
     *
     * phpcs:disable Inpsyde.CodeQuality.ArgumentTypeDeclaration
     */
    public function withLocalize(string $objectName, $data): Script
    {
        // phpcs:enable Inpsyde.CodeQuality.ArgumentTypeDeclaration

        $this->localize[$objectName] = $data;

        return $this;
    }

    /**
     * @return bool
     */
    public function inFooter(): bool
    {
        return $this->inFooter;
    }

    /**
     * @return static
     */
    public function isInFooter(): Script
    {
        $this->inFooter = true;

        return $this;
    }

    /**
     * @return static
     */
    public function isInHeader(): Script
    {
        $this->inFooter = false;

        return $this;
    }

    /**
     * @return array{before:string[], after:string[]}
     */
    public function inlineScripts(): array
    {
        return $this->inlineScripts;
    }

    /**
     * @param string $jsCode
     *
     * @return static
     */
    public function prependInlineScript(string $jsCode): Script
    {
        $this->inlineScripts['before'][] = $jsCode;

        return $this;
    }

    /**
     * @param string $jsCode
     *
     * @return static
     */
    public function appendInlineScript(string $jsCode): Script
    {
        $this->inlineScripts['after'][] = $jsCode;

        return $this;
    }

    /**
     * @return array{domain:string, path:string|null}
     */
    public function translation(): array
    {
        return $this->translation;
    }

    /**
     * @param string $domain
     * @param string|null $path
     *
     * @return static
     */
    public function withTranslation(string $domain = 'default', string $path = null): Script
    {
        $this->translation = ['domain' => $domain, 'path' => $path];

        return $this;
    }

    /**
     * Wrapper function to set AsyncScriptOutputFilter as filter.
     *
     * @return static
     * @deprecated use Script::withAttributes(['async' => true]);
     */
    public function useAsyncFilter(): Script
    {
        $this->withAttributes(['async' => true]);

        return $this;
    }

    /**
     * Wrapper function to set DeferScriptOutputFilter as filter.
     *
     * @return static
     * @deprecated use Script::withAttributes(['defer' => true]);
     */
    public function useDeferFilter(): Script
    {
        $this->withAttributes(['defer' => true]);

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    protected function defaultHandler(): string
    {
        return ScriptHandler::class;
    }

    /**
     * Automatically resolving dependencies for JS files by searching for a file named
     *
     *  - {fileName}.asset.json
     *  - {fileName}.asset.php
     *
     * which contains an array of dependencies and the version.
     *
     * @see https://github.com/WordPress/gutenberg/tree/master/packages/dependency-extraction-webpack-plugin
     */
    public function useDependencyExtractionPlugin(): Script
    {
        $this->useDependencyExtractionPlugin = true;

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function version(): ?string
    {
        $this->resolveDependencyExtractionPlugin();

        return parent::version();
    }

    /**
     * {@inheritDoc}
     */
    public function dependencies(): array
    {
        $this->resolveDependencyExtractionPlugin();

        return parent::dependencies();
    }

    /**
     * @return bool
     *
     * @see Script::useDependencyExtractionPlugin()
     * @psalm-suppress MixedArrayAccess, PossiblyFalseArgument
     */
    protected function resolveDependencyExtractionPlugin(): bool
    {
        if ($this->resolvedDependencyExtractionPlugin) {
            return false;
        }

        $filePath = $this->filePath();
        $depsPhpFile = str_replace(".js", ".asset.php", $filePath);
        $depsJsonFile = str_replace(".js", ".asset.json", $filePath);

        $data = [];
        if (file_exists($depsPhpFile)) {
            $data = @require $depsPhpFile; // phpcs:ignore
        } elseif (file_exists($depsJsonFile)) {
            $data = @json_decode(@file_get_contents($depsJsonFile), true); // phpcs:ignore
        }

        /** @var string[] $dependencies */
        $dependencies = $data['dependencies'] ?? [];
        /** @var string|null $version */
        $version = $data['version'] ?? null;

        $this->withDependencies(...$dependencies);
        if (!$this->version && $version) {
            $this->withVersion((string) $version);
        }

        $this->resolvedDependencyExtractionPlugin = true;

        return true;
    }
}
