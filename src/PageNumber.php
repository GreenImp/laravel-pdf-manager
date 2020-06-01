<?php

namespace GreenImp\PdfManager;

use GreenImp\PdfManager\Enums\PagesEnum;
use JsonSerializable;

class PageNumber implements JsonSerializable
{
    /** @var int $startOffset Offset from the start of the document */
    protected $startOffset = 0;

    /** @var int $endOffset Offset from the end of the document */
    protected $endOffset = 0;

    /** @var PagesEnum $page A generic page type (i.e All, First, Even) */
    protected $page;

    /** @var array|null $pageNumbers List of page numbers */
    protected $pageNumbers;

    /**
     * PageNumber constructor.
     *
     * @param  PagesEnum|array  $pages
     */
    public function __construct($pages = null)
    {
        if ($pages instanceof PagesEnum) {
            $this->setPage($pages);
        } elseif (is_array($pages) && !empty($pages)) {
            if (isset($pages['start']) || isset($pages['end'])) {
                $this->setOffset($pages['start'] ?? 0, $pages['end'] ?? 0);
            } else {
                $this->setPageNumbers(...$pages);
            }
        }
    }

    /**
     * Checks whether the given page.
     *
     * @param  int  $pageNumber
     * @param  int  $pageCount
     *
     * @return bool
     */
    protected function isInRange(int $pageNumber, int $pageCount): bool
    {
        return ($pageNumber > $this->startOffset) && ($pageNumber <= $pageCount - $this->endOffset);
    }

    /**
     * Checks if the given page number matches the Page requirements.
     *
     * @param  int  $pageNumber
     * @param  int  $pageCount
     *
     * @return bool
     */
    protected function isPage(int $pageNumber, int $pageCount): bool
    {
        if ($this->page instanceof PagesEnum) {
            switch ($this->page) {
                case PagesEnum::ALL:
                    return true;
                    break;
                case PagesEnum::FIRST:
                    return $pageNumber == 1;
                    break;
                case PagesEnum::LAST:
                    return $pageNumber == $pageCount;
                    break;
                case PagesEnum::EVEN:
                    return $pageNumber % 2 == 0;
                    break;
                case PagesEnum::ODD:
                    return $pageNumber % 2 != 0;
                    break;
            }
        }

        return false;
    }

    /**
     * Resets all the defined values.
     */
    protected function resetValues()
    {
        // reset the offsets
        $this->startOffset = null;
        $this->endOffset = null;

        // reset the page
        $this->page = null;

        // reset the page numbers
        $this->pageNumbers = null;
    }

    /**
     * Checks if the page number is contained in the specs for this PageNumber.
     *
     * @param  int  $pageNumber
     * @param  int  $pageCount
     *
     * @return bool
     */
    public function contains(int $pageNumber, int $pageCount): bool
    {
        if ($this->page instanceof PagesEnum) {
            // generic page specified
            return $this->isPage($pageNumber, $pageCount);
        } elseif (!empty($this->pageNumbers)) {
            // specific page numbers specified
            return in_array($pageNumber, $this->pageNumbers);
        }

        // default to checking the offsets
        return $this->isInRange($pageNumber, $pageCount);
    }

    /**
     * Returns the defined page offsets as
     * ```
     * [
     *     'start' => 0,
     *     'end' => 0,
     * ]
     * ```.
     *
     * @return array
     */
    public function getOffset(): array
    {
        return [
            'start' => $this->startOffset,
            'end' => $this->endOffset,
        ];
    }

    /**
     * Returns the specific page type, if defined.
     *
     * @return PagesEnum|null
     */
    public function getPage(): ?PagesEnum
    {
        return $this->page;
    }

    /**
     * Returns a list of the specific page numbers, if defined.
     *
     * @return array|null
     */
    public function getPageNumbers(): ?array
    {
        return $this->pageNumbers;
    }

    /**
     * Sets the page offsets.
     *
     * @param  int  $start
     * @param  int  $end
     *
     * @return $this
     */
    public function setOffset(int $start = 0, int $end = 0): self
    {
        // reset the values
        $this->resetValues();

        // set the offsets
        $this->startOffset = (!empty($start) && ($start > 0)) ? $start : 0;
        $this->endOffset = (!empty($end) && ($end > 0)) ? $end : 0;

        return $this;
    }

    /**
     * Sets the pages as a generic type (ie. All, First, Even, etc.).
     *
     * @param  PagesEnum  $page
     *
     * @return $this
     */
    public function setPage(PagesEnum $page): self
    {
        // reset the values
        $this->resetValues();

        // set the page
        $this->page = $page;

        return $this;
    }

    /**
     * Sets specific page numbers.
     *
     * @param  int  ...$pageNumbers
     *
     * @return $this
     */
    public function setPageNumbers(int ...$pageNumbers): self
    {
        // reset the values
        $this->resetValues();

        $this->pageNumbers = $pageNumbers;

        return $this;
    }

    /**
     * Returns the JSON serializable version of the object.
     *
     * @return array|mixed
     */
    public function jsonSerialize()
    {
        return [
            'offset' => $this->getOffset(),
            'page' => $this->getPage(),
            'pageNumbers' => $this->getPageNumbers(),
        ];
    }
}
