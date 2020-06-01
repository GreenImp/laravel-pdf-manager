<?php

namespace GreenImp\PdfManager\Stamps;

use GreenImp\PdfManager\Enums\PagePositionEnum;
use SetaPDF_Core_Document_Page;
use SetaPDF_Stamper_Stamp;

class PageNumbers extends TextStamp
{
    public const DIVIDER = ' of ';
    public const POSTFIX = '';
    public const PREFIX = 'Page ';
    public const POSITION = PagePositionEnum::BOTTOM_RIGHT;

    /** @var string|null $divider */
    protected $divider;

    /** @var string|null $postfix */
    protected $postfix;

    /** @var string|null $prefix */
    protected $prefix;

    public function __construct(
        ?string $prefix = self::PREFIX,
        ?string $divider = self::DIVIDER,
        ?string $postfix = self::POSTFIX,
        float $fontSize = self::FONT_SIZE,
        ?string $fontColour = self::FONT_COLOUR,
        ?PagePositionEnum $position = null,
        int $pageStartOffset = 0,
        int $pageEndOffset = 0
    ) {
        parent::__construct(
            $fontSize,
            $fontColour,
            $position ?? new PagePositionEnum(self::POSITION),
            ['start' => $pageStartOffset, 'end' => $pageEndOffset]
        );

        $this->prefix = $prefix;
        $this->postfix = $postfix;
        $this->divider = $divider;

        // set the default axis offsets
        // @todo - this should probably change depending on the PagePosition - these values are specific for bottom-right
        $this->setOffset(-30, 10);
    }

    /**
     * Returns the text used to stamp the page number.
     *
     * @param  int  $pageNumber  The current page number (Offset by page start/end offset)
     * @param  int  $pageCount  The total number of pages (Offset by the start/end offset)
     * @param  int  $actualPageNumber  The actual current page number not affected by offsets
     * @param  int  $actualPageCount  The actual total number of pages, not affected by offsets
     * @param  SetaPDF_Core_Document_Page  $page
     * @param  string|null  $prefix
     * @param  string|null  $divider
     * @param  string|null  $postfix
     *
     * @return string|null
     */
    protected function getPageNumberText(
        int $pageNumber,
        int $pageCount,
        int $actualPageNumber,
        int $actualPageCount,
        SetaPDF_Core_Document_Page $page,
        ?string $prefix = null,
        ?string $divider = null,
        ?string $postfix = null
    ): ?string {
        return $prefix . $pageNumber . $divider . $pageCount . $postfix;
    }

    protected function stampCallback(
        int $actualPageNumber,
        int $actualPageCount,
        SetaPDF_Core_Document_Page $page,
        SetaPDF_Stamper_Stamp $stamp
    ): bool {
        $pageOffset = $this->pageOffset->getOffset() ?? ['start' => 0, 'end' => 0];

        // set the offset page numbers if we're ignoring any pages at the start or end of the file
        $offsetPageNumber = $actualPageNumber - $pageOffset['start'];
        $offsetPageCount = $actualPageCount - $pageOffset['start'] - $pageOffset['end'];

        // build the text to display the page number
        $text = $this->getPageNumberText(
            $offsetPageNumber,
            $offsetPageCount,
            $actualPageNumber,
            $actualPageCount,
            $page,
            $this->prefix,
            $this->divider,
            $this->postfix
        );

        // if text was returned use it
        if (!empty($text)) {
            $stamp->settext($text);

            return true;
        }

        return false;
    }
}
