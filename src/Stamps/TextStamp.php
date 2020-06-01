<?php

namespace GreenImp\PdfManager\Stamps;

use BenSampo\Enum\Exceptions\InvalidEnumMemberException;
use GreenImp\PdfManager\Enums\PagePositionEnum;
use GreenImp\PdfManager\Enums\PagesEnum;
use GreenImp\PdfManager\PageNumber;
use InvalidArgumentException;
use SetaPDF_Core_Document;
use SetaPDF_Core_Font_FontInterface;
use SetaPDF_Core_Font_Standard_Helvetica;
use SetaPDF_Stamper_Stamp;
use SetaPDF_Stamper_Stamp_Text;

// @todo allow setting of font

/**
 * Stamps text on a PDF
 *
 * @package GreenImp\PdfManager\Stamps
 */
class TextStamp extends Stampable
{
    public const FONT_COLOUR = '#888888';
    public const FONT_SIZE = 8;

    /** @var string $fontColour */
    protected $fontColour;

    /** @var float $fontSize */
    protected $fontSize;

    /**
     * TextStamp constructor.
     *
     * @param  float  $fontSize
     * @param  string|null  $fontColour
     * @param  PagePositionEnum|null  $position
     * @param  PageNumber|PagesEnum|array|null  $pages
     *
     * @throws InvalidEnumMemberException
     */
    public function __construct(
        float $fontSize = self::FONT_SIZE,
        ?string $fontColour = self::FONT_COLOUR,
        ?PagePositionEnum $position = null,
        $pages = null
    ) {
        parent::__construct($position, $pages);

        $this->setFontSize($fontSize ?? self::FONT_SIZE);
        $this->setFontColour($fontColour ?? self::FONT_COLOUR);
    }

    /**
     * Returns the font object for the document.
     *
     * @param  SetaPDF_Core_Document  $document
     *
     * @return SetaPDF_Core_Font_FontInterface
     */
    protected function getFont(SetaPDF_Core_Document $document): SetaPDF_Core_Font_FontInterface
    {
        return SetaPDF_Core_Font_Standard_Helvetica::create($document);
    }

    /**
     * {@inheritdoc}
     */
    protected function getStamp(SetaPDF_Core_Document $document): SetaPDF_Stamper_Stamp
    {
        $font = $this->getFont($document);
        $stamp = new SetaPDF_Stamper_Stamp_Text($font, $this->fontSize ?? self::FONT_SIZE);

        $stamp->setTextColor($this->fontColour ?? self::FONT_COLOUR);

        return $stamp;
    }

    /**
     * Sets the font colour for the page number text.
     *
     * @param  string  $fontColour
     */
    public function setFontColour(string $fontColour)
    {
        // check that the font colour is valid
        $fontColour = $fontColour ? ltrim('' . $fontColour, '#') : null;
        if (empty($fontColour) || !ctype_xdigit($fontColour) || (strlen($fontColour) != 6)) {
            throw new InvalidArgumentException('$fontColour must be a 6 digit hexadecimal value');
        }

        // set the font colour
        $this->fontColour = '#' . $fontColour;
    }

    /**
     * Sets the font size for the page number text.
     *
     * @param  float  $fontSize
     *
     * @return $this
     */
    public function setFontSize(float $fontSize): self
    {
        if ($fontSize <= 0) {
            throw new InvalidArgumentException('$fontSize must be a positive number');
        }

        $this->fontSize = $fontSize;

        return $this;
    }
}
