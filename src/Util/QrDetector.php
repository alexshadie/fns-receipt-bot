<?php
namespace alexshadie\FnsReceiptBot\Util;


use Libern\QRCodeReader\QRCodeReader;

class QrDetector
{
    private $filename;
    private $qrCodeReader;
    private $tmpPath = TMP_PATH . "/";
    private $threshold = 0.5;

    public function __construct(string $filename)
    {
        $this->filename = $filename;
        $this->qrCodeReader = new QRCodeReader();
    }

    public function processImage()
    {
        $u = uniqid();

        exec("convert \"{$this->filename}\" -adaptive-resize 2000x2000 -type Grayscale {$this->tmpPath}{$u}_1.png");
        exec("convert {$this->tmpPath}{$u}_1.png -fuzz 40% -fill white -opaque white {$this->tmpPath}{$u}_2.png");
        exec("convert {$this->tmpPath}{$u}_2.png -crop 200x200 {$this->tmpPath}{$u}_tile%04d.png");

        $output = [];
        exec("convert -format \"%[entropy]:%X%Y:%f\n\" {$this->tmpPath}{$u}_tile*.png info:", $output);

        $tiles = [];

        foreach ($output as $line) {
            $matches = [];
            if (
            preg_match("!^(?P<entropy>(0\.\d+|-nan))\:\+(?P<x>\d+)\+(?P<y>\d+)\:(?P<filename>.*)$!", $line, $matches)
            ) {
                $tiles[$matches['x']][$matches['y']] =
                    floatval($matches['entropy'])
                ;
            }
        }

        $this->dumpTiles($tiles);

        $images = $this->detectTiles($tiles, $u);
        $data = $this->detectQrCode($images);

        exec("rm {$this->tmpPath}/{$u}_*");

        return $data;
    }

    private function dumpTiles($tiles) {
        foreach ($tiles as $row) {
            foreach ($row as $col) {
                if ($col > $this->threshold) {
                    echo "1";
                } else {
                    echo "0";
                }
            }
            echo "\n";
        }
        echo "\n\n=========================\n\n";
    }

    public function detectTiles($tiles, $u) {
        $ranges = [];
        $height = count($tiles);
        $width = count($tiles[0]);

        // cover matrix by 3x3 to min($width, $height) squares

        $allSquares = [];

        for ($coverSize = 3; $coverSize < min($width, $height); $coverSize++) {
            for ($i = 0; $i < $width - $coverSize; $i++) {
                for ($j = 0; $j < $height - $coverSize; $j++) {
                    $sum = 0;
                    for ($ii = 0; $ii < $coverSize; $ii++) {
                        for ($jj = 0; $jj < $coverSize; $jj++) {
                            $sum += $tiles[200 * ($j + $jj)][200 * ($i + $ii)] > $this->threshold ? 1 : 0;
                        }
                    }

                    if ($sum >= 2 * $coverSize * $coverSize / 3) {
                        $allSquares[] = [$i, $j, $coverSize, $sum];
                    }
                }
            }
        }

        $images = [];

        foreach ($allSquares as $k => $square) {
            $x1 = $square[0] * 200 - 200;
            $y1 = $square[1] * 200 - 200;
            if ($x1 < 0) $x1 = 0;
            if ($y1 < 0) $y1 = 0;
            $d = (2 + $square[2]) * 200;

            exec("convert -crop {$d}x{$d}+{$y1}+{$x1} {$this->tmpPath}{$u}_1.png {$this->tmpPath}{$u}_out_{$k}.png");
            $images[] = "{$u}_out_{$k}.png";
        }

        return $images;
    }

    public function detectQrCode($images)
    {
        foreach ($images as $image) {
            $data = $this->qrCodeReader->decode("{$this->tmpPath}{$image}");
            if ($data) {
                return $data;
            }
        }
        return false;
    }
}
