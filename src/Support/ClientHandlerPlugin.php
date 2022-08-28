<?php

namespace ZenCodex\ComposerMirror\Support;

use League\Flysystem\Plugin\AbstractPlugin;

/**
 * Class ImagePreviewPlugin
 * 
 * @package ZenCodex\Support\Flysystem\Plugin
 */
class ClientHandlerPlugin extends AbstractPlugin
{
    /**
     * Get the method name.
     *
     * @return string
     */
    public function getMethod()
    {
        return 'getClientHandler';
    }

    public function handle($configMore = [])
    {
        return $this->filesystem->getAdapter()->getClientHandler($configMore);
    }
}