<?php

namespace GreenImp\PdfManager;

use GreenImp\PdfManager\Enums\FieldModifierEnum;
use GreenImp\PdfManager\Exceptions\FileMergeException;
use GreenImp\PdfManager\Exceptions\FileReadException;
use GreenImp\PdfManager\Exceptions\FileSaveException;
use GreenImp\PdfManager\Exceptions\InvalidFieldException;
use GreenImp\PdfManager\Exceptions\InvalidFileException;
use GreenImp\PdfManager\Exceptions\InvalidMimeTypeException;
use GreenImp\PdfManager\Stamps\PageNumbers;
use GreenImp\PdfManager\Stamps\Stampable;
use DomPDF;
use Exception;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use SetaPDF_Core_Document;
use SetaPDF_Core_Exception;
use SetaPDF_Core_Parser_CrossReferenceTable_Exception;
use SetaPDF_Core_Reader_Exception;
use SetaPDF_Core_Writer_String;
use SetaPDF_FormFiller;
use SetaPDF_FormFiller_Exception;
use SetaPDF_FormFiller_Fields;
use SetaPDF_Merger;

class PdfManager
{
    /** @var array $data */
    protected $data = [];

    /** @var array $fieldProperties */
    protected $fieldProperties = [];

    /** @var Filesystem $fileSystem */
    protected $fileSystem;

    /**
     * List of files added to the manager.
     *
     * @var array
     */
    protected $files = [];

    /** @var array $formFillers A cached list of PDF document form filler objects for documents */
    protected $formFillers = [];

    /** @var PageNumbers|null $pageNumbers */
    protected $pageNumbers;

    /** @var array $stamps */
    protected $stamps = [];

    /**
     * The path within the filesystem to store created PDFs.
     *
     * @var string
     */
    public const STORAGE_PATH = 'pdf/generated/';

    public function __construct(?Filesystem $fileSystem = null)
    {
        $this->fileSystem = $fileSystem ?? Storage::disk();
    }

    /**
     * Returns a PDF document writer.
     *
     * @return SetaPDF_Core_Writer_String
     */
    protected function getFileWriter(): SetaPDF_Core_Writer_String
    {
        return new SetaPDF_Core_Writer_String();
    }

    /**
     * Returns a PDF document form filler object for the document.
     *
     * @param  SetaPDF_Core_Document  $document
     *
     * @return SetaPDF_FormFiller
     */
    protected function getFormFiller(SetaPDF_Core_Document $document): SetaPDF_FormFiller
    {
        $hash = spl_object_hash($document);

        // cache the result so we always get the same form filler for a given document
        if (!isset($this->formFillers[$hash])) {
            $this->formFillers[$hash] = new SetaPDF_FormFiller($document);
        }

        return $this->formFillers[$hash];
    }

    /**
     * Recursively creates the given directory, if it doesn't exist.
     *
     * @param $directory
     *
     * @return bool
     */
    protected function makeDirectory($directory): bool
    {
        // ensure that the document storage directory exists
        if (!$this->fileSystem->exists($directory)) {
            return $this->fileSystem->makeDirectory($directory);
        }

        return true;
    }

    /**
     * Validates that the file path is valid.
     * It must exist, and be a PDF.
     *
     * @param  string  $filePath
     *
     * @return bool
     *
     * @throws FileNotFoundException
     * @throws InvalidMimeTypeException
     */
    protected function validateFile(string $filePath): bool
    {
        if (!$this->fileSystem->exists($filePath)) {
            throw new FileNotFoundException($this->fileSystem->path($filePath));
        }

        $mimeType = $this->fileSystem->mimeType($filePath);
        if ($mimeType != 'application/pdf') {
            throw new InvalidMimeTypeException($mimeType, 'application/pdf');
        }

        return true;
    }

    /**
     * Adds the file path to the list.
     * Throws an exception if the file doesn't exist.
     * Returns $this for chainability.
     *
     * @param  string  $filePath
     * @param  float|null  $order  [optional] The order / position that the file should be rendered at
     *
     * @return $this
     *
     * @throws FileNotFoundException
     * @throws InvalidMimeTypeException
     */
    public function addFile(string $filePath, ?float $order = null): self
    {
        $this->validateFile($filePath);

        $this->files['' . $order] = $filePath;

        return $this;
    }

    /**
     * Adds the files tyo the list.
     * Throws an exception if any of the files don't exist
     * Returns $this for chainability.
     *
     * @param  string  ...$filePaths
     *
     * @return $this
     *
     * @throws FileNotFoundException
     * @throws InvalidMimeTypeException
     */
    public function addFiles(string ...$filePaths): self
    {
        foreach ($filePaths as $filePath) {
            if (is_array($filePath)) {
                $path = $filePath['path'];
                $order = $filePath['order'] ?? null;
            } else {
                $path = $filePath;
                $order = null;
            }

            $this->addFile($path, $order);
        }

        return $this;
    }

    /**
     * Build a PDF from the given view name and add it to the manager
     * Returns the file path relative to the storage disk.
     *
     * If `$fileName` is NOT specified, one is generated from the view name,
     * current time, and a unique identifier
     *
     * @param  string  $viewName
     * @param  array|null  $data  [optional] data to pass to the blade view
     * @param  string|null  $fileName  [optional] Use to specify a particular filename
     * @param  float|null  $order  [optional] The order / position that the file should be rendered at
     *
     * @return string
     *
     * @throws FileNotFoundException
     * @throws InvalidMimeTypeException
     */
    public function addView(
        string $viewName,
        ?array $data = null,
        ?string $fileName = null,
        ?float $order = null
    ): string {
        // build the PDF
        $filePath = $this->buildView($viewName, $data, $fileName);

        // add the file to the list
        $this->addFile($filePath, $order);

        // return the file path
        return $filePath;
    }

    /**
     * Builds a single PDF file by merging together all files added to the manager, using the
     * data, page numbers etc that have ben set on the object.
     *
     * @param  string  $fileName  The filename to save the PDF as (Can include a relative path)
     * @param  bool  $ignoreMissingFields  [optional] Whether to ignore missing form fields instead of throwing an error
     *
     * @return string
     *
     * @throws FileMergeException
     * @throws FileNotFoundException
     * @throws FileSaveException
     * @throws InvalidFieldException
     * @throws InvalidFileException
     * @throws InvalidMimeTypeException
     * @throws SetaPDF_Core_Exception
     */
    public function build(string $fileName, bool $ignoreMissingFields = false): string
    {
        if (empty($fileName)) {
            throw new InvalidArgumentException('$fileName must not be empty');
        }

        $outputPath = $this->storagePath(FileNameHelper::appendExtension($fileName, 'pdf'));
        $files = $this->getFiles();

        $data = $this->data;

        $fieldProperties = $this->fieldProperties;

        $stamps = $this->stamps;
        if (!is_null($this->pageNumbers)) {
            $stamps[] = $this->pageNumbers;
        }

        if (empty($files)) {
            // no files
            throw new InvalidArgumentException('No files added to manager');
        } elseif (count($files) > 1) {
            // we have more than 1 document - merge them together
            $document = $this->merge($files);
        } else {
            // only a single file - load it
            $document = $this->loadFile(reset($files));
        }

        // fill the form
        if (!empty($data)) {
            $document = $this->fillForm($document, $data, $ignoreMissingFields);
        }

        // update field properties (ie. readonly, flatten)
        if (!empty($fieldProperties)) {
            $document = $this->modifyFields($document, $fieldProperties, $ignoreMissingFields);
        }

        // add page numbers and any other stamps
        if (!empty($stamps)) {
            $document = $this->stamp($document, ...$stamps);
        }

        // save the file
        $this->saveFile($outputPath, $document);

        // return the stored file path
        return $outputPath;
    }

    /**
     * Builds a single PDF file by merging together all files added to the manager,
     * filling form fields that match the $data and saves it to storage.
     *
     * This can be called multiple times with different data, to generate multiple PDF files
     * with the same pages, but different filled forms, rather than rebuilding the manager
     * each time.
     *
     * @param  string  $fileName  The filename to save the PDF as (Can include a relative path)
     * @param  array|null  $data  [optional] form field data to populate the PDF with
     * @param  PageNumbers  $pageNumbers  [optional] object to add page numbers to the document
     * @param  bool  $ignoreMissingFields  [optional] Whether to ignore missing form fields instead of throwing an error
     *
     * @return string
     *
     * @throws FileMergeException
     * @throws FileNotFoundException
     * @throws FileSaveException
     * @throws InvalidFieldException
     * @throws InvalidFileException
     * @throws InvalidMimeTypeException
     * @throws SetaPDF_Core_Exception
     *
     * @deprecated use `build()` method instead
     */
    public function buildFile(
        string $fileName,
        ?array $data = null,
        ?PageNumbers $pageNumbers = null,
        bool $ignoreMissingFields = false
    ): string {
        return $this
            ->setData($data)
            ->setPageNumbers($pageNumbers)
            ->build($fileName, $ignoreMissingFields);
    }

    /**
     * Build a PDF from the given view name.
     * Returns the file path relative to the storage disk.
     *
     * If `$fileName` is NOT specified, one is generated from the view name,
     * current time, and a unique identifier
     *
     * @param  string  $viewName
     * @param  array|null  $data  [optional] data to pass to the blade view
     * @param  string|null  $fileName  [optional] Use to specify a particular filename
     *
     * @return string
     *
     * @throws FileNotFoundException
     * @throws FileSaveException
     */
    public function buildView(string $viewName, ?array $data = null, ?string $fileName = null): string
    {
        // load the view as a PDF document
        /** @var \Barryvdh\DomPDF\PDF $pdf */
        $pdf = DomPDF::loadView($viewName, $data ?? []);

        // determine the file name to store it as
        if (!empty($fileName)) {
            // ensure that the filename ends with `.pdf`
            $outputName = FileNameHelper::appendExtension($fileName, 'pdf');
        } else {
            // create a unique filename
            $outputName = FileNameHelper::generateFileName('pdf', str_replace('.', '-', $viewName));
        }
        $outputPath = $this->storagePath($outputName);

        // ensure the storage directory exist
        if (!$this->makeDirectory(self::STORAGE_PATH)) {
            // error creating directory
            throw new FileNotFoundException('Storage directory not found and cannot be created: ' . self::STORAGE_PATH);
        }

        // set the page size
        $pdf->setPaper('a4', 'portrait');

        /**
         * DomPDF `save()` method can`t handle non-local disks (e.g. s3)
         * so we need to get the file contents and save it manually
         */
        // get the file contents
        $contents = $pdf->output();

        // store the file
        if (!$this->fileSystem->put($outputPath, $contents)) {
            throw new FileSaveException($outputPath);
        }

        // return the relative output path
        return $outputPath;
    }

    /**
     * Fills the form fields in the given document with the provided data.
     * An error will be thrown if no fields are specified, or if any
     * fields do not exist in the document.
     *
     * @param  SetaPDF_Core_Document  $document
     * @param  array  $data
     * @param  bool  $ignoreMissing  [optional] Whether to ignore missing fields instead of throwing an error
     *
     * @return SetaPDF_Core_Document
     *
     * @throws InvalidFieldException
     * @throws SetaPDF_Core_Exception
     */
    public function fillForm(
        SetaPDF_Core_Document $document,
        array $data,
        bool $ignoreMissing = false
    ): SetaPDF_Core_Document {
        if (empty($data)) {
            throw new InvalidArgumentException('$data must not be empty');
        }

        $formFiller = $this->getFormFiller($document);
        $fields = $formFiller->getFields();

        // loop through each data item and update the related form field value
        foreach ($data as $key => $value) {
            // an error is thrown if we try and set a form field that doesn't exist
            try {
                $fields->get($key)->setValue($value);
            } catch (SetaPDF_FormFiller_Exception $e) {
                // only throw the exception if we want to
                if (!$ignoreMissing) {
                    throw new InvalidFieldException($key, $e);
                }
            }
        }

        // return the document
        return $document;
    }

    /**
     * Returns the filesystem that the manager is using.
     *
     * @return Filesystem
     */
    public function getDisk(): Filesystem
    {
        return $this->fileSystem;
    }

    /**
     * Returns the form fields found within the given PDF document.
     *
     * @param  SetaPDF_Core_Document  $document
     *
     * @return SetaPDF_FormFiller_Fields
     */
    public function getFields(SetaPDF_Core_Document $document): SetaPDF_FormFiller_Fields
    {
        return $this->getFormFiller($document)->getFields();
    }

    /**
     * Returns the names of every form field in the document.
     * Useful for debugging.
     *
     * @param  SetaPDF_Core_Document  $document
     *
     * @return array
     */
    public function getFieldNames(SetaPDF_Core_Document $document): array
    {
        return $this->getFields($document)->getNames();
    }

    /**
     * Returns a list of all the document's fields and their SetaPDF field type.
     *
     * @param  SetaPDF_Core_Document  $document
     *
     * @return array
     */
    public function getFieldTypes(SetaPDF_Core_Document $document): array
    {
        return array_map(
            function ($item) {
                return class_basename($item);
            },
            $this->getFields($document)->getAll()
        );
    }

    /**
     * Returns the files attached to the manager.
     *
     * @return array
     */
    public function getFiles(): array
    {
        // ensure files are sorted by keys (These define the order)
        ksort($this->files);

        return $this->files;
    }

    /**
     * Loads the PDF and returns an accessible document object.
     *
     * @param  string  $filePath
     *
     * @return SetaPDF_Core_Document
     *
     * @throws FileNotFoundException
     * @throws FileReadException
     * @throws InvalidMimeTypeException
     * @throws InvalidFileException
     */
    public function loadFile(string $filePath): SetaPDF_Core_Document
    {
        $this->validateFile($filePath);

        // create the writer instance
        $writer = $this->getFileWriter();

        // load the document and return it
        try {
            // load the document contents and return it
            // we can't load by path here, with `SetaPDF_Core_Document::loadByFilename()`,
            // because SetaSign can't load from cloud disks (e.g. s3)
            return SetaPDF_Core_Document::loadByString($this->fileSystem->get($filePath), $writer);
        } catch (SetaPDF_Core_Reader_Exception $e) {
            throw new FileReadException($filePath, $e);
        } catch (SetaPDF_Core_Parser_CrossReferenceTable_Exception $e) {
            throw new InvalidFileException($filePath, $e);
        }
    }

    /**
     * Loads a collection of files and returns them as an array.
     *
     * @param  string  ...$filePaths
     *
     * @return SetaPDF_Core_Document[]
     *
     * @throws FileNotFoundException
     * @throws InvalidMimeTypeException
     * @throws InvalidFileException
     */
    public function loadFiles(string ...$filePaths): array
    {
        if (empty($filePaths)) {
            throw new InvalidArgumentException('$filePaths must not be empty');
        }

        // loop through and load each file
        return array_map(
            function ($filePath) {
                return $this->loadFile($filePath);
            },
            $filePaths
        );
    }

    /**
     * Takes a list of PDF file paths and merges them together.
     * Returns a document object.
     *
     * @param  string[]|SetaPDF_Core_Document[]  ...$files
     * @param  bool  $renameFields  [optional] whether form fields with the same name in different documents should be renamed. Defaults to false
     *
     * @return SetaPDF_Core_Document
     *
     * @throws FileMergeException
     * @throws FileNotFoundException
     * @throws FileReadException
     * @throws InvalidFileException
     * @throws InvalidMimeTypeException
     */
    public function merge($files, bool $renameFields = false): SetaPDF_Core_Document
    {
        if (empty($files)) {
            throw new InvalidArgumentException('$files must not be empty');
        }

        // create a merger instance
        $merger = new SetaPDF_Merger();

        // By default, ensure that form fields with the same name, in different documents, are kept
        $merger->setRenameSameNamedFormFields($renameFields);

        // loop through each file and merge it in
        foreach ($files as $file) {
            if ($file instanceof SetaPDF_Core_Document) {
                // file is already loaded so we can just merge it it
                $merger->addDocument($file);
            } else {
                // load the document and add it to the merger
                // we can't use `$merger->addFile()` in case the file isn't local (e.g. s3)
                $document = $this->loadFile($file);
                $merger->addDocument($document);
            }
        }

        // several different errors could be thrown whilst merging - catch them all and throw our own error
        try {
            $merger->merge();
        } catch (Exception $e) {
            throw new FileMergeException($e->getMessage(), $e->getCode(), $e);
        }

        return $merger->getDocument();
    }

    /**
     * Modifies form field properties such as readonly state, flattens and deletes fields.
     *
     * @param  SetaPDF_Core_Document  $document
     * @param  array  $fieldProperties
     * @param  bool  $ignoreMissing  [optional] Whether to ignore missing fields instead of throwing an error
     *
     * @return SetaPDF_Core_Document
     *
     * @throws InvalidFieldException
     * @throws SetaPDF_Core_Exception
     */
    public function modifyFields(
        SetaPDF_Core_Document $document,
        array $fieldProperties,
        bool $ignoreMissing = false
    ): SetaPDF_Core_Document {
        if (empty($fieldProperties)) {
            return $document;
        }

        $formFiller = $this->getFormFiller($document);
        $fields = $formFiller->getFields();

        foreach ($fieldProperties as $name => $properties) {
            // this intentionally only catches errors thrown when getting the field (Missing filed)
            // and doesn't catch any errors thrown when actually updating the field properties.
            // If the field exists then errors on updating should be visible
            try {
                $field = $fields->get($name);
            } catch (SetaPDF_FormFiller_Exception $e) {
                // only throw the exception if we want to
                if (!$ignoreMissing) {
                    throw new InvalidFieldException($name, $e);
                } else {
                    // don't throw error but skip to the next field
                    continue;
                }
            }

            if ($properties[FieldModifierEnum::DELETE] ?? false) {
                // delete the field
                $field->delete();
            } elseif ($properties[FieldModifierEnum::FLATTEN] ?? false) {
                // flatten the field
                $field->flatten();
            } elseif (isset($properties[FieldModifierEnum::READ_ONLY])) {
                // set the readonly state (true or false)
                $field->setReadOnly((bool)$properties[FieldModifierEnum::READ_ONLY]);
            } elseif (isset($properties[FieldModifierEnum::REQUIRED])) {
                // set the required state (true or false)
                $field->setRequired((bool)$properties[FieldModifierEnum::REQUIRED]);
            }
        }

        // return the document
        return $document;
    }

    /**
     * Removes the filepath from the list.
     *
     * @param  string  $filePath
     */
    public function removeFile(string $filePath)
    {
        $k = array_search($filePath, $this->files);

        if (false !== $k) {
            unset($this->files[$k]);

            // reset the array keys
            $this->files = array_values($this->files);
        }
    }

    /**
     * Saves the document to the given path.
     *
     * @param  string  $path
     * @param  SetaPDF_Core_Document  $document
     *
     * @return bool
     *
     * @throws FileSaveException
     * @throws SetaPDF_Core_Exception
     */
    public function saveFile(string $path, SetaPDF_Core_Document $document): bool
    {
        $path = FileNameHelper::appendExtension($path, 'pdf');

        // finalise file editing and get the contents
        $document->setWriter($this->getFileWriter());
        $document->save()->finish();
        $contents = $document->getWriter()->getBuffer();

        // free the resources and unlock files
        $document->cleanUp();

        // store the file
        if (!$this->fileSystem->put($path, $contents)) {
            throw new FileSaveException($path);
        }

        return true;
    }

    /**
     * Sets the data mapping to be filled when building a file.
     * Returns $this for chainability.
     *
     * @param  array  $data
     *
     * @return $this
     */
    public function setData(?array $data): self
    {
        $this->data = $data;

        return $this;
    }

    /**
     * Specify which fields should be flattened when building a file.
     * Returns $this for chainability.
     *
     * @param  string  ...$fields
     *
     * @return $this
     */
    public function setFlattenFields(string ...$fields): self
    {
        foreach ($fields as $field) {
            $this->fieldProperties[$field][FieldModifierEnum::FLATTEN] = true;
        }

        return $this;
    }

    /**
     * Sets the page numbers object to use when building a file.
     * Returns $self for chainability.
     *
     * @param  PageNumbers|null  $pageNumbers
     *
     * @return $this
     */
    public function setPageNumbers(?PageNumbers $pageNumbers): self
    {
        $this->pageNumbers = $pageNumbers;

        return $this;
    }

    /**
     * Specify which fields should be removed when building a file.
     * Returns $this for chainability.
     *
     * @param  string  ...$fields
     *
     * @return $this
     */
    public function setDeleteFields(string ...$fields): self
    {
        foreach ($fields as $field) {
            $this->fieldProperties[$field][FieldModifierEnum::DELETE] = true;
        }

        return $this;
    }

    /**
     * Adds stamp(s) for the build process.
     *
     * @param  Stampable  ...$stamp
     *
     * @return $this
     */
    public function setStamp(Stampable ...$stamp): self
    {
        $this->stamps = array_merge($this->stamps, $stamp);

        return $this;
    }

    /**
     * Stamps the document with the given stamps.
     *
     * @param  SetaPDF_Core_Document  $document
     * @param  Stampable  ...$stamps
     *
     * @return SetaPDF_Core_Document
     */
    public function stamp(SetaPDF_Core_Document $document, Stampable ...$stamps): SetaPDF_Core_Document
    {
        foreach ($stamps as $stamp) {
            $document = $stamp->stamp($document);
        }

        return $document;
    }

    /**
     * Get the full path, from root, to the PDF storage folder.
     *
     * ie. var/www/app/storage/app/pdf/generated/$path
     *
     * @param  string|null  $path
     *
     * @return string
     */
    public function fullStoragePath(?string $path): string
    {
        $path = $this->storagePath($path);

        return $this->getDisk()->path($path);
    }

    /**
     * Return the relative storage path.
     *
     * ie. pdf/generated/$path
     *
     * @param  string|null  $path
     *
     * @return string
     */
    public function storagePath(?string $path): string
    {
        // remove leading slashes
        $path = $path ? ltrim($path, DIRECTORY_SEPARATOR) : null;

        // if $path already stars with the storage path, then just return it
        if ($path && Str::startsWith($path, self::STORAGE_PATH)) {
            return $path;
        }

        return self::STORAGE_PATH . $path;
    }
}
