<?php

namespace Application\Logger;

use InvalidArgumentException;

class File
{
    const MAX_SIZE = 1048576; // 1 megabyte

    private $fh;
    private $path;
    private $date;
    private $filename;
    private $prefix;
    private $suffix = 'log';

    public function __construct($path, $prefix = null)
    {
        if (null === $path) {
            throw new InvalidArgumentException(sprintf(
                'filepath required'
            ));
        }

        if (!file_exists($path)) {
            throw new InvalidArgumentException(sprintf(
                'path "%s" could not be found',
                $path
            ));
        }

        if (!is_writable($path)) {
            throw new InvalidArgumentException(sprintf(
                'path "%s" is not writeable',
                $path
            ));
        }

        $this->path   = $path;
        $this->prefix = $prefix;
        $this->date   = date('Y-m-d');

        $this->createFileHandle();
    }

    public function write($msg)
    {
        $this->checkLogName();
        $this->doWrite($msg);
    }

    public function writeLn($msg = '')
    {
        $this->write($msg . "\n");
    }

    protected function checkLogName()
    {
        $stat = fstat($this->fh);
        $size = $stat['size'];

        $date = date('Y-m-d');
        if ($date !== $this->date) {
            $this->date = $date;
            $this->createFileHandle();
        }

        if ($size > self::MAX_SIZE) {
            $this->doWrite("max filesize reached, splitting file...\n");
            $pattern = sprintf('logs/%s.*', $this->filename);
            $files   = glob($pattern);

            $count = 1;
            if (!empty($files)) {
                $last  = $files[count($files) - 1];
                $count = (int) substr($last, strrpos($last, '.') + 1) + 1;
            }

            // close and rename old file
            fclose($this->fh);
            rename('logs/' . $this->filename, 'logs/' . $this->filename . '.' . $count);
            $this->createFileHandle();
        }
    }

    protected function createFileHandle()
    {
        if (is_resource($this->fh)) {
            fclose($this->fh);
        }

        if ($this->prefix) {
            $this->filename = sprintf('%s-%s.%s', $this->prefix, $this->date, $this->suffix);
        } else {
            $this->filename = sprintf('%s.%s', $this->date, $this->suffix);
        }
        $this->fh = fopen(sprintf('%s/%s', $this->path, $this->filename), 'a');
    }

    protected function doWrite($msg)
    {
        fwrite($this->fh, sprintf('[%s] %s', date('H:i:s'), $msg));
    }
}
