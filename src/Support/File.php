<?php // Support/File.php

namespace Nedieyassin\MessagingGateway\Support;

use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

final readonly class File
{
    public function __construct(public string $contents, public string $name)
    {
    }

    public static function fromPath(string $path, ?string $name = null): self
    {
        $contents = is_file($path) ? file_get_contents($path) : false;
        if ($contents === false) {
            throw new InvalidArgumentException("Cannot read file [{$path}].");
        }
        return new self($contents, $name ?? basename($path));
    }

    public static function fromData(string $data, string $name): self
    {
        return new self($data, $name);
    }

    public static function fromStorage(string $disk, string $path, ?string $name = null): self
    {
        $contents = Storage::disk($disk)->get($path);
        if ($contents === null) {
            throw new InvalidArgumentException("Cannot read [{$path}] from disk [{$disk}].");
        }
        return new self($contents, $name ?? basename($path));
    }
}