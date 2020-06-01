<?php

namespace GreenImp\PdfManager\Stamps;

use GreenImp\PdfManager\Enums\PagePositionEnum;
use GreenImp\PdfManager\Enums\PagesEnum;
use GreenImp\PdfManager\PageNumber;
use BenSampo\Enum\Exceptions\InvalidEnumMemberException;
use InvalidArgumentException;
use SetaPDF_Core_Document;
use SetaPDF_Core_Document_Page;
use SetaPDF_Stamper;
use SetaPDF_Stamper_Stamp;

/**
 * Base stampable functionality
 *
 * @package GreenImp\PdfManager\Stamps
 */
abstract class Stampable
{
    /** @var PagePositionEnum|null $position */
    protected $position;

    /** @var PageNumber|null the page numbers / offsets to place the stamp on */
    protected $pageOffset;

    /** @var array $positionOffsetX The X axis offset from the defined position */
    protected $positionOffsetX = 0;

    /** @var array $positionOffsetY The Y axis offset from the defined position */
    protected $positionOffsetY = 0;

    /** @var string POSITION The default stamp position on the page */
    public const POSITION = PagePositionEnum::TOP_CENTRE;

    /**
     * Stampable constructor.
     *
     * @param  PagePositionEnum|null  $position
     * @param  PageNumber|PagesEnum|array|null  $pages
     *
     * @throws InvalidEnumMemberException
     */
    public function __construct(?PagePositionEnum $position = null, $pages = null)
    {
        $this->setPosition($position ?? new PagePositionEnum(self::POSITION));

        if (!is_null($pages)) {
            $this->setPages($pages);
        }
    }

    /**
     * Returns the position details in a format that SetaSign requires.
     *
     * @return array
     */
    protected function getInternalPosition()
    {
        return [
            'position' => $this->position,
            'offset' => [
                'x' => $this->positionOffsetX,
                'y' => $this->positionOffsetY,
            ],
        ];
    }

    /**
     * Returns the PDF document stamper object for the document.
     *
     * @param  SetaPDF_Core_Document  $document
     *
     * @return SetaPDF_Stamper
     */
    protected function getStamper(SetaPDF_Core_Document $document): SetaPDF_Stamper
    {
        return new SetaPDF_Stamper($document);
    }

    /**
     * Returns the stamp object.
     *
     * @param  SetaPDF_Core_Document  $document
     *
     * @return SetaPDF_Stamper_Stamp
     */
    abstract protected function getStamp(SetaPDF_Core_Document $document): SetaPDF_Stamper_Stamp;

    /**
     * Callback for modifying the stamp on a per-page basis, or forcing certain pages not to be stamped.
     *
     * To not stamp a given page, this function should return false.
     *
     * @param  int  $actualPageNumber
     * @param  int  $actualPageCount
     * @param  SetaPDF_Core_Document_Page  $page
     * @param  SetaPDF_Stamper_Stamp  $stamp
     *
     * @return bool
     */
    protected function stampCallback(
        int $actualPageNumber,
        int $actualPageCount,
        SetaPDF_Core_Document_Page $page,
        SetaPDF_Stamper_Stamp $stamp
    ): bool {
        return true;
    }

    /**
     * Sets the X and Y axis position offset.
     *
     * @param  float  $x
     * @param  float  $y
     *
     * @return $this
     */
    public function setOffset(float $x, float $y): self
    {
        $this->setXOffset($x);
        $this->setYOffset($y);

        return $this;
    }

    /**
     * Sets the page offset from the start / end of the document for the numbering,
     * allowing pages to be skipped.
     *
     * @param  int|null  $pageStartOffset
     * @param  int|null  $pageEndOffset
     *
     * @return $this
     */
    public function setPageOffset(?int $pageStartOffset = null, ?int $pageEndOffset = null): self
    {
        $this->setPages(
            [
                'start' => (!empty($pageStartOffset) && ($pageStartOffset > 0)) ? $pageStartOffset : 0,
                'end' => (!empty($pageEndOffset) && ($pageEndOffset > 0)) ? $pageEndOffset : 0,
            ]
        );

        return $this;
    }

    /**
     * Sets the pages to stamp.
     *
     * @param  PageNumber|PagesEnum|array  $pages
     *
     * @return $this
     */
    public function setPages($pages): self
    {
        $this->pageOffset = ($pages instanceof PageNumber) ? $pages : new PageNumber($pages);

        return $this;
    }

    /**
     * Sets the page position to place the page numbers.
     *
     * @param  PagePositionEnum  $position
     *
     * @return $this
     */
    public function setPosition(PagePositionEnum $position): self
    {
        $this->position = $position;

        return $this;
    }

    /**
     * Sets the X axis position offset.
     *
     * @param  float  $offset
     *
     * @return $this
     *
     * @throws InvalidArgumentException
     */
    public function setXOffset(float $offset): self
    {
        $this->positionOffsetX = $offset;

        return $this;
    }

    /**
     * Sets the Y axis position offset.
     *
     * @param  float  $offset
     *
     * @return $this
     *
     * @throws InvalidArgumentException
     */
    public function setYOffset(float $offset): self
    {
        $this->positionOffsetY = $offset;

        return $this;
    }

    /**
     * Stamps the given document.
     *
     * @param  SetaPDF_Core_Document  $document
     *
     * @return SetaPDF_Core_Document
     */
    public function stamp(SetaPDF_Core_Document $document): SetaPDF_Core_Document
    {
        $stamper = $this->getStamper($document);
        $stamp = $this->getStamp($document);
        $position = $this->getInternalPosition();

        // determine which pages to stamp on
        if ($this->pageOffset instanceof PageNumber) {
            $showOnPage = function (int $pageNumber, int $pageCount) {
                return $this->pageOffset->contains($pageNumber, $pageCount);
            };
        } else {
            $showOnPage = PagesEnum::ALL;
        }

        $stamper->addStamp(
            $stamp,
            [
                'position' => $position['position'],
                'translateX' => $position['offset']['x'],
                'translateY' => $position['offset']['y'],
                'showOnPage' => $showOnPage,
                'callback' => function (
                    int $actualPageNumber,
                    int $actualPageCount,
                    SetaPDF_Core_Document_Page $page,
                    SetaPDF_Stamper_Stamp $stamp
                ) {
                    return $this->stampCallback($actualPageNumber, $actualPageCount, $page, $stamp);
                },
            ]
        );

        // stamp the document with all added stamps of the stamper
        $stamper->stamp();

        return $document;
    }
}
