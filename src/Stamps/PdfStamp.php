<?php

namespace GreenImp\PdfManager\Stamps;

use GreenImp\PdfManager\Enums\PagePositionEnum;
use App\Libraries\PdfManager\Enums\Pages;
use App\Libraries\PdfManager\PageNumber;
use InvalidArgumentException;
use SetaPDF_Core_Document;
use SetaPDF_Stamper_Stamp;
use SetaPDF_Stamper_Stamp_Pdf;

/**
 * Stamps a PDF on top of another PDF
 *
 * @package GreenImp\PdfManager\Stamps
 */
class PdfStamp extends Stampable
{
    /** @var float|null $height The height to stamp the PDF file as */
    protected $height = null;

    /** @var string $path Path to the PDF file to stamp with */
    protected $path;

    /** @var float|null $width The width to stamp the PDF as */
    protected $width = null;

    /**
     * PdfStamp constructor.
     *
     * @param  string|null  $path [optional] path to the pdf to stamp with
     * @param  PagePositionEnum|null  $position
     * @param  PageNumber|Pages|array|null $pages
     *
     * @throws \BenSampo\Enum\Exceptions\InvalidEnumMemberException
     *
     * @todo this wont work if stamp path is on a cloud disk (e.g. s3)
     */
    public function __construct(?string $path = null, ?PagePositionEnum $position = null, $pages = null)
    {
        parent::__construct($position, $pages);

        $this->setPath($path);
    }

    /**
     * Returns the stamp object.
     *
     * @param SetaPDF_Core_Document $document
     *
     * @return SetaPDF_Stamper_Stamp
     */
    protected function getStamp(SetaPDF_Core_Document $document): SetaPDF_Stamper_Stamp
    {
        $stamp = new SetaPDF_Stamper_Stamp_Pdf($this->path);

        // specify the stamp dimensions
        if ($this->width) {
            $stamp->setWidth($this->width);
        }
        if ($this->height) {
            $stamp->setHeight($this->height);
        }

        return $stamp;
    }

    /**
     * Resets the width and height of the stamp to their defaults
     *
     * @return $this
     */
    public function resetDimensions(): self
    {
        $this->setDimensions(null, null);

        return $this;
    }

    /**
     * Resets the height of the stamp to the default
     *
     * @return $this
     */
    public function resetHeight(): self
    {
        $this->setHeight(null);

        return $this;
    }

    /**
     * Resets the width of the stamp to the default
     *
     * @return $this
     */
    public function resetWidth(): self
    {
        $this->setWidth(null);

        return $this;
    }

    /**
     * Sets the width and height for the stamp.
     * Set either value to `null` to use the default.
     *
     * @param  float  $width
     * @param  float  $height
     *
     * @return $this
     */
    public function setDimensions(?float $width, ?float $height): self
    {
        $this->setWidth($width);
        $this->setHeight($height);

        return $this;
    }

    /**
     * Sets the stamp height.
     * Set to `null` to reset to default.
     *
     * @param  float|null  $height
     *
     * @return $this
     *
     * @throws InvalidArgumentException
     */
    public function setHeight(?float $height): self
    {
        if (is_numeric($height) && ($height <= 0)) {
            throw new InvalidArgumentException('$height must be a positive number or null');
        }

        $this->height = $height;

        return $this;
    }

    /**
     * Sets the file path for the PDF to stamp with.
     *
     * @param  string  $path
     *
     * @return $this
     */
    public function setPath(string $path): self
    {
        $this->path = $path;

        return $this;
    }

    /**
     * Sets the stamp width.
     * Set to `null` to reset to default.
     *
     * @param  float|null  $width
     *
     * @return $this
     *
     * @throws InvalidArgumentException
     */
    public function setWidth(?float $width): self
    {
        if (is_numeric($width) && ($width <= 0)) {
            throw new InvalidArgumentException('$width must be a positive number or null');
        }

        $this->width = $width;

        return $this;
    }
}
