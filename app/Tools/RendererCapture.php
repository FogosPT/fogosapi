<?php

namespace App\Tools;

class RendererCapture
{
    private ?string $path;

    private string $bytes;

    public function __construct(string $path, string $bytes)
    {
        $this->path = $path;
        $this->bytes = $bytes;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function bytes(): string
    {
        return $this->bytes;
    }

    public function cleanup(): void
    {
        if ($this->path !== null && file_exists($this->path)) {
            @unlink($this->path);
        }
        $this->path = null;
    }

    public function __destruct()
    {
        $this->cleanup();
    }
}
