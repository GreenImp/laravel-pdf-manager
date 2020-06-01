<?php

namespace GreenImp\PdfManager\Tests;

use App\Libraries\PdfManager\Exceptions\FileMergeException;
use App\Libraries\PdfManager\Exceptions\InvalidFieldException;
use App\Libraries\PdfManager\Exceptions\InvalidFileException;
use App\Libraries\PdfManager\Exceptions\InvalidMimeTypeException;
use App\Libraries\PdfManager\PdfManager;
use ArgumentCountError;
use Carbon\Carbon;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use SetaPDF_Core_Document;
use SetaPDF_FormFiller_Fields;
use Tests\TestCase;
use TypeError;

class PdfManagerTest extends TestCase
{
    use WithFaker;

    /** @var Filesystem $fileSystem */
    protected $fileSystem;

    protected $pdfFiles = [
        'real-pdf-01.pdf',
        'real-pdf-02.pdf',
    ];

    /** @var PdfManager $pdfManager */
    protected $pdfManager;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('pdfs');
        $this->fileSystem = Storage::disk('pdfs');

        $this->pdfManager = new PdfManager($this->fileSystem);

        // create some pdf and non-pdf files
        for ($i = 1; $i <= 3; $i++) {
            // create a valid PDF
            $pdfFile = UploadedFile::fake()->create('dummy-'.$i.'.pdf', 1);
            $this->fileSystem->putFileAs('', $pdfFile, 'dummy-'.$i.'.pdf');

            // create a non-PDF file
            $textFile = UploadedFile::fake()->create('dummy-'.$i.'.txt', 1);
            $this->fileSystem->putFileAs('', $textFile, 'dummy-'.$i.'.txt');
        }

        // create a test storage handler for our base real PDF files
        // this is for read only access so we can copy base files into the actual PDFs directory
        Storage::persistentFake('base');
        $baseDisk = Storage::disk('base');

        // loop through our base files and copy them to the testing directory
        foreach ($baseDisk->allFiles() as $file) {
            $name = explode('/', $file);

            $this->fileSystem->put(end($name), $baseDisk->get($file));
        }
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        // delete all the created files
        foreach ($this->fileSystem->allFiles() as $file) {
            $this->fileSystem->delete($file);
        }
    }

    /*******************************************************
     * Initial tests
     *******************************************************/

    public function testCanCreatePdfManager()
    {
        $this->assertInstanceOf(PdfManager::class, $this->pdfManager);
    }

    public function testCanGetDisk()
    {
        $this->assertInstanceOf(Filesystem::class, $this->pdfManager->getDisk());
    }

    /*******************************************************
     * Adding files tests
     *******************************************************/

    public function testCanAddPdf()
    {
        $filePath = 'dummy-1.pdf';

        $this->pdfManager->addFile($filePath);

        $this->assertCount(1, $this->pdfManager->getFiles());
        $this->assertEquals(
            [
                $filePath,
            ],
            $this->pdfManager->getFiles()
        );
    }

    public function testAddingNonExistentFileThrowsError()
    {
        $this->expectException(FileNotFoundException::class);

        $this->pdfManager->addFile('no-file.pdf');
    }

    public function testCanAddMultipleFilesIndividually()
    {
        $filePath1 = 'dummy-1.pdf';
        $filePath2 = 'dummy-2.pdf';

        $this->pdfManager->addFile($filePath1);
        $this->pdfManager->addFile($filePath2);

        $this->assertCount(2, $this->pdfManager->getFiles());
        $this->assertEquals(
            [
                $filePath1,
                $filePath2,
            ],
            $this->pdfManager->getFiles()
        );
    }

    public function testCanAddMultipleFilesInBatch()
    {
        $filePaths = [
           'dummy-1.pdf',
           'dummy-2.pdf',
        ];

        $this->pdfManager->addFiles(...$filePaths);

        $this->assertCount(2, $this->pdfManager->getFiles());
        $this->assertEquals($filePaths, $this->pdfManager->getFiles());
    }

    public function testAddingSingleInBatchIsAdded()
    {
        $filePath = 'dummy-1.pdf';

        $this->pdfManager->addFiles($filePath);

        $this->assertCount(1, $this->pdfManager->getFiles());
        $this->assertEquals([$filePath], $this->pdfManager->getFiles());
    }

    public function testAddingBatchInSingleThrowsError()
    {
        $this->expectException(\TypeError::class);

        $filePaths = [
           'dummy-1.pdf',
           'dummy-2.pdf',
        ];

        $this->pdfManager->addFile($filePaths);
    }

    public function testAddingNonPdfFileThrowsError()
    {
        $this->expectException(InvalidMimeTypeException::class);

        $this->pdfManager->addFile('dummy-1.txt');
    }

    /*******************************************************
     * Removing files tests
     *******************************************************/

    public function testCanRemovePdf()
    {
        $filePath = 'dummy-1.pdf';

        $this->pdfManager->addFile($filePath);

        // just double-check that it has been added
        $this->assertCount(1, $this->pdfManager->getFiles());

        // remove the file
        $this->pdfManager->removeFile($filePath);

        $this->assertCount(0, $this->pdfManager->getFiles());
        $this->assertEquals([], $this->pdfManager->getFiles());
    }

    public function testCanRemoveSinglePdfWhenMultipleExist()
    {
        $filePath1 = 'dummy-1.pdf';
        $filePath2 = 'dummy-2.pdf';

        $this->pdfManager->addFile($filePath1);
        $this->pdfManager->addFile($filePath2);

        // just double-check that it has been added
        $this->assertCount(2, $this->pdfManager->getFiles());

        // remove the first file
        $this->pdfManager->removeFile($filePath1);

        $this->assertCount(1, $this->pdfManager->getFiles());
        $this->assertEquals(
            [
                $filePath2,
            ],
            $this->pdfManager->getFiles()
        );
    }

    public function testRemovingFileOnEmptyListPdfDoesNothing()
    {
        // remove a non-existent file
        $this->pdfManager->removeFile('no-file.pdf');

        $this->assertCount(0, $this->pdfManager->getFiles());
        $this->assertEquals([], $this->pdfManager->getFiles());
    }

    public function testRemovingNonExistentFileListPdfDoesNothing()
    {
        $filePath = 'dummy-1.pdf';

        $this->pdfManager->addFile($filePath);

        // remove a non-existent file
        $this->pdfManager->removeFile('no-file.pdf');

        // assert added file still exists
        $this->assertCount(1, $this->pdfManager->getFiles());
        $this->assertEquals(
            [
                $filePath,
            ],
            $this->pdfManager->getFiles()
        );
    }

    /*******************************************************
     * Building PDFs from views / blade files
     *******************************************************/

    public function testCanBuildFromView()
    {
        $viewName = 'pdf.test.basic';
        $filePath = $this->pdfManager->buildView($viewName);

        $fileName = explode('/', $filePath);
        $fileName = end($fileName);

        // assert that the file has been created
        $this->assertTrue($this->fileSystem->exists($filePath));
        $this->assertEquals('application/pdf', $this->fileSystem->mimeType($filePath));

        // assert that the file name is correct
        $this->assertStringStartsWith(str_replace('.', '-', $viewName).'_'.Carbon::now()->format('Y-m-d_H'), $fileName);
        $this->assertStringEndsWith('.pdf', $fileName);
    }

    public function testCanAddFromView()
    {
        $viewName = 'pdf.test.basic';
        $filePath = $this->pdfManager->addView($viewName);

        $fileName = explode('/', $filePath);
        $fileName = end($fileName);

        // assert that the file has been created
        $this->assertTrue($this->fileSystem->exists($filePath));
        $this->assertEquals('application/pdf', $this->fileSystem->mimeType($filePath));

        // assert that the file name is correct
        $this->assertStringStartsWith(str_replace('.', '-', $viewName).'_'.Carbon::now()->format('Y-m-d_H'), $fileName);
        $this->assertStringEndsWith('.pdf', $fileName);

        // assert that the file has been added to the list
        $this->assertCount(1, $this->pdfManager->getFiles());
        $this->assertEquals(
            [
                $filePath,
            ],
            $this->pdfManager->getFiles()
        );
    }

    public function testCanSpecifyNameWhenBuildingView()
    {
        $viewName = 'pdf.test.basic';
        $storeFileName = 'test-123.pdf';

        $filePath = $this->pdfManager->buildView($viewName, null, $storeFileName);

        // assert that the file has been created
        $this->assertTrue($this->fileSystem->exists($filePath));

        // assert that the filename is correct
        $this->assertStringEndsWith('/'.$storeFileName, $filePath);
    }

    public function testCanSpecifyNameWhenAddingView()
    {
        $viewName = 'pdf.test.basic';
        $storeFileName = 'test-123.pdf';

        $filePath = $this->pdfManager->addView($viewName, null, $storeFileName);

        // assert that the file has been created
        $this->assertTrue($this->fileSystem->exists($filePath));

        // assert that the filename is correct
        $this->assertStringEndsWith('/'.$storeFileName, $filePath);

        // assert that the file has been added to the list
        $this->assertCount(1, $this->pdfManager->getFiles());
        $this->assertEquals(
            [
                $filePath,
            ],
            $this->pdfManager->getFiles()
        );
    }

    public function testSpecifyNameWithoutExtensionGetsPDFAppended()
    {
        $viewName = 'pdf.test.basic';
        $storeFileName = 'test-123';

        $filePath = $this->pdfManager->buildView($viewName, null, $storeFileName);

        // assert that the file has been created
        $this->assertTrue($this->fileSystem->exists($filePath));

        // assert that the filename is correct
        $this->assertStringEndsWith('/'.$storeFileName.'.pdf', $filePath);
    }

    public function testSpecifyNameWithIncorrectExtensionGetsPDFAppended()
    {
        $viewName = 'pdf.test.basic';
        $storeFileName = 'test-123.foo';

        $filePath = $this->pdfManager->buildView($viewName, null, $storeFileName);

        // assert that the file has been created
        $this->assertTrue($this->fileSystem->exists($filePath));

        // assert that the filename is correct
        $this->assertStringEndsWith('/'.$storeFileName.'.pdf', $filePath);
    }

    public function testCanBuildFromViewWithData()
    {
        // load a view that we know requires a `$list` variable that is an array
        // if it creates it without throwing an error then it must be okay
        $viewName = 'pdf.test.has-data';
        $data = [
            'list' => [
                'foo',
                'bar',
            ],
        ];
        $filePath = $this->pdfManager->buildView($viewName, $data);

        // assert that the file has been created
        $this->assertTrue($this->fileSystem->exists($filePath));
        $this->assertEquals('application/pdf', $this->fileSystem->mimeType($filePath));
    }

    public function testCanAddFromViewWithData()
    {
        // load a view that we know requires a `$list` variable that is an array
        // if it creates it without throwing an error then it must be okay
        $viewName = 'pdf.test.has-data';
        $data = [
            'list' => [
                'foo',
                'bar',
            ],
        ];
        $filePath = $this->pdfManager->addView($viewName, $data);

        // assert that the file has been created
        $this->assertTrue($this->fileSystem->exists($filePath));
        $this->assertEquals('application/pdf', $this->fileSystem->mimeType($filePath));

        // assert that the file has been added to the list
        $this->assertCount(1, $this->pdfManager->getFiles());
        $this->assertEquals(
            [
                $filePath,
            ],
            $this->pdfManager->getFiles()
        );
    }

    /*******************************************************
     * Loading PDFs
     *******************************************************/

    public function testCanLoadPDFDocumentWriter()
    {
        $filePath = $this->pdfFiles[0];

        $document = $this->pdfManager->loadFile($filePath);

        $this->assertInstanceOf(SetaPDF_Core_Document::class, $document);
    }

    public function testLoadNonExistentDocumentThrowsError()
    {
        $this->expectException(FileNotFoundException::class);

        $this->pdfManager->loadFile('no-file.pdf');
    }

    public function testLoadNonPDFDocumentThrowsError()
    {
        $this->expectException(InvalidMimeTypeException::class);

        $this->pdfManager->loadFile('dummy-1.txt');
    }

    public function testLoadInvalidPdfThrowsError()
    {
        $this->expectException(InvalidFileException::class);

        // try and load one of the empty PDF files
        $this->pdfManager->loadFile('dummy-1.pdf');
    }

    public function testCanLoadMultipleFiles()
    {
        $filePaths = [
            $this->pdfFiles[0],
            $this->pdfFiles[1],
        ];

        $documents = $this->pdfManager->loadFiles(...$filePaths);

        $this->assertCount(2, $documents);

        foreach ($documents as $document) {
            $this->assertInstanceOf(SetaPDF_Core_Document::class, $document);
        }
    }

    /*******************************************************
     * Saving PDFs
     *******************************************************/

    public function testCanSaveLoadedPdf()
    {
        $filePath = $this->pdfFiles[0];
        // create a unique name to test with
        $outputName = 'save-me-'.Str::uuid().'.pdf';

        // assert that the file doesn't already exist
        $this->assertFalse($this->fileSystem->exists($outputName));

        // load the document
        $document = $this->pdfManager->loadFile($filePath);

        // and save the document
        $result = $this->pdfManager->saveFile($outputName, $document);

        // assert that the file has been created
        $this->assertTrue($result);
        $this->assertTrue($this->fileSystem->exists($outputName));
        $this->assertEquals('application/pdf', $this->fileSystem->mimeType($outputName));
    }

    public function testSavingInvalidObjectThrowsError()
    {
        $this->expectException(TypeError::class);

        $outputName = 'invalid-object-'.Str::uuid().'.pdf';

        // assert that the file doesn't already exist
        $this->assertFalse($this->fileSystem->exists($outputName));

        $this->pdfManager->saveFile($outputName, 'foo');
    }

    public function testSavingPdfWithoutPdfExtensionAppendsPdf()
    {
        $filePath = $this->pdfFiles[0];
        // create a unique name to test with
        $outputName = 'no-extension-'.Str::uuid();

        // assert that the file doesn't already exist
        $this->assertFalse($this->fileSystem->exists($outputName.'.pdf'));

        // load the document
        $document = $this->pdfManager->loadFile($filePath);

        // and save the document
        $result = $this->pdfManager->saveFile($outputName, $document);

        // assert that the file has been created
        $this->assertTrue($result);
        $this->assertTrue($this->fileSystem->exists($outputName.'.pdf'));
        $this->assertEquals('application/pdf', $this->fileSystem->mimeType($outputName.'.pdf'));
    }

    public function testSavingPdfWithIncorrectExtensionAppendsPdf()
    {
        $filePath = $this->pdfFiles[0];
        // create a unique name to test with
        $outputName = 'no-extension-'.Str::uuid().'.txt';

        // assert that the file doesn't already exist
        $this->assertFalse($this->fileSystem->exists($outputName));

        // load the document
        $document = $this->pdfManager->loadFile($filePath);

        // and save the document
        $result = $this->pdfManager->saveFile($outputName, $document);

        // assert that the file has been created
        $this->assertTrue($result);
        $this->assertTrue($this->fileSystem->exists($outputName.'.pdf'));
        $this->assertEquals('application/pdf', $this->fileSystem->mimeType($outputName.'.pdf'));
    }

    /*******************************************************
     * Filling PDF Forms
     *******************************************************/

    public function testCanGetFormFields()
    {
        $filePath = $this->pdfFiles[0];
        // load the document
        $document = $this->pdfManager->loadFile($filePath);

        // get the form fields
        $fields = $this->pdfManager->getFields($document);

        // assert that form fields were found
        $this->assertInstanceOf(SetaPDF_FormFiller_Fields::class, $fields);
        $this->assertNotEmpty($fields->getAll());
    }

    public function testCanChangeFormFieldValue()
    {
        $data = [
            'company_name' => $this->faker->company,
            'address' => str_replace("\n", ' ', $this->faker->address),
            'postcode' => $this->faker->postcode,
        ];

        $filePath = $this->pdfFiles[0];
        // load the document
        $document = $this->pdfManager->loadFile($filePath);

        // store the initial form field values
        $initialValues = array_map(function ($item) {
            return $item->getValue();
        }, $this->pdfManager->getFields($document)->getAll());

        // update the form fields
        $result = $this->pdfManager->fillForm($document, $data);

        // get the current values
        $currentValues = array_map(function ($item) {
            return $item->getValue();
        }, $this->pdfManager->getFields($document)->getAll());

        // assert that the object was returned
        $this->assertInstanceOf(SetaPDF_Core_Document::class, $result);
        // ensure that the data has changed
        $this->assertnotEquals($initialValues, $currentValues);

        // loop through each data item and ensure that it both exists and is set correctly
        foreach ($data as $k => $v) {
            $this->assertArrayHasKey($k, $currentValues);
            $this->assertEquals($v, $currentValues[$k]);
        }
    }

    public function testFillingFormWithNoDataThrowsError()
    {
        $this->expectException(InvalidArgumentException::class);

        $filePath = $this->pdfFiles[0];
        // load the document
        $document = $this->pdfManager->loadFile($filePath);

        // update the form fields
        $this->pdfManager->fillForm($document, []);
    }

    public function testCanOverrideFieldValuesWithEmpty()
    {
        $data = [
            'company_name' => $this->faker->company,
            'address' => str_replace("\n", ' ', $this->faker->address),
            'postcode' => $this->faker->postcode,
        ];

        $filePath = $this->pdfFiles[0];
        // load the document
        $document = $this->pdfManager->loadFile($filePath);

        // update the form fields
        $result = $this->pdfManager->fillForm($document, $data);

        // get the current values
        $currentValues = array_map(function ($item) {
            return $item->getValue();
        }, $this->pdfManager->getFields($document)->getAll());

        // loop through each data item and ensure that it both exists and is set correctly
        foreach ($data as $k => $v) {
            $this->assertArrayHasKey($k, $currentValues);
            $this->assertEquals($v, $currentValues[$k]);
        }

        // now reset the form fields
        $result = $this->pdfManager->fillForm($document, array_map(function () {
            return '';
        }, $data));

        // get the current values
        $currentValues = array_map(function ($item) {
            return $item->getValue();
        }, $this->pdfManager->getFields($document)->getAll());

        // loop through each data item and ensure that it is empty
        foreach ($data as $k => $v) {
            $this->assertEmpty($currentValues[$k]);
        }
    }

    public function testSettingNonExistentFormFieldThrowsError()
    {
        $this->expectException(InvalidFieldException::class);

        $filePath = $this->pdfFiles[0];
        // load the document
        $document = $this->pdfManager->loadFile($filePath);

        // update the form fields
        $this->pdfManager->fillForm($document, ['Foo' => 'bar']);
    }

    public function testCanSaveFilledPdf()
    {
        $data = [
            'company_name' => $this->faker->company,
            'address' => str_replace("\n", ' ', $this->faker->address),
            'postcode' => $this->faker->postcode,
        ];
        $filePath = $this->pdfFiles[0];
        // create a unique name to test with
        $outputName = 'save-me-'.Str::uuid().'.pdf';

        // assert that the file doesn't already exist
        $this->assertFalse($this->fileSystem->exists($outputName));

        // load the document
        $document = $this->pdfManager->loadFile($filePath);

        // update the form fields
        $document = $this->pdfManager->fillForm($document, $data);

        // save the document
        $result = $this->pdfManager->saveFile($outputName, $document);

        // assert that the file has been created
        $this->assertTrue($result);
        $this->assertTrue($this->fileSystem->exists($outputName));
        $this->assertEquals('application/pdf', $this->fileSystem->mimeType($outputName));
    }

    /*******************************************************
     * Merging PDFs
     *******************************************************/

    public function testCanMergePdfsFromFileName()
    {
        $files = [
            $this->pdfFiles[0],
            $this->pdfFiles[1],
        ];

        $document = $this->pdfManager->merge($files);

        $this->assertInstanceOf(SetaPDF_Core_Document::class, $document);
    }

    public function testCanMergePdfsFromDocumentInstance()
    {
        $files = $this->pdfManager->loadFiles(...[
            $this->pdfFiles[0],
            $this->pdfFiles[1],
        ]);

        $document = $this->pdfManager->merge($files);

        $this->assertInstanceOf(SetaPDF_Core_Document::class, $document);
    }

    public function testCanMergePdfsFromFileNameAndDocumentInstance()
    {
        $files = [
            $this->pdfFiles[0],
            $this->pdfManager->loadFile($this->pdfFiles[1]),
        ];

        $document = $this->pdfManager->merge($files);

        $this->assertInstanceOf(SetaPDF_Core_Document::class, $document);
    }

    public function testMergingInvalidPdfThrowsError()
    {
        $this->expectException(FileMergeException::class);

        $this->pdfManager->merge(['dummy-1.pdf']);
    }

    public function testMergingNonPdfThrowsError()
    {
        $this->expectException(InvalidMimeTypeException::class);

        $this->pdfManager->merge(['dummy-1.txt']);
    }

    public function testMergingNoExistentPdfThrowsError()
    {
        $this->expectException(FileNotFoundException::class);

        $this->pdfManager->merge(['no-file.pdf']);
    }

    public function testMergingEmptyListThrowsError()
    {
        $this->expectException(InvalidArgumentException::class);

        $this->pdfManager->merge([]);
    }

    public function testMergingNothingThrowsError()
    {
        $this->expectException(ArgumentCountError::class);

        $this->pdfManager->merge();
    }

    public function testCanSaveMergedDocument()
    {
        $files = [
            $this->pdfFiles[0],
            $this->pdfFiles[1],
        ];
        // create a unique name to test with
        $outputName = 'save-me-'.Str::uuid().'.pdf';

        // assert that the file doesn't already exist
        $this->assertFalse($this->fileSystem->exists($outputName));

        $document = $this->pdfManager->merge($files);

        // save the document
        $result = $this->pdfManager->saveFile($outputName, $document);

        // assert that the file has been created
        $this->assertTrue($result);
        $this->assertTrue($this->fileSystem->exists($outputName));
        $this->assertEquals('application/pdf', $this->fileSystem->mimeType($outputName));
    }

    /*******************************************************
     * Building PDFs
     *******************************************************/

    public function testCanBuildPdf()
    {
        $data = [
            'company_name' => $this->faker->company,
            'address' => str_replace("\n", ' ', $this->faker->address),
            'postcode' => $this->faker->postcode,
        ];

        // create a unique name to test with
        $outputName = 'save-me-'.Str::uuid().'.pdf';

        // assert that the file doesn't already exist
        $this->assertFalse($this->fileSystem->exists($outputName));

        // add the file
        $this->pdfManager->addFile($this->pdfFiles[0]);

        $result = $this->pdfManager->buildFile($outputName, $data);

        // assert that the file has been created
        $this->assertStringEndsWith('/'.$outputName, $result);
        $this->assertTrue($this->fileSystem->exists($result));
        $this->assertEquals('application/pdf', $this->fileSystem->mimeType($result));
    }

    public function testCanBuildPdfWithMultiplePdfs()
    {
        $filePaths = [
            $this->pdfFiles[0],
            $this->pdfFiles[1],
        ];
        $viewNames = [
            'pdf.test.basic',
        ];

        $data = [
            'company_name' => $this->faker->company,
            'address' => str_replace("\n", ' ', $this->faker->address),
            'postcode' => $this->faker->postcode,
        ];

        // create a unique name to test with
        $outputName = 'save-me-'.Str::uuid().'.pdf';

        // assert that the file doesn't already exist
        $this->assertFalse($this->fileSystem->exists($outputName));

        // add the files from path names
        $this->pdfManager->addFiles(...$filePaths);

        // load the views as PDFs and add them to the list
        foreach ($viewNames as $viewName) {
            $this->pdfManager->addView($viewName);
        }

        // assert that all the files have been added
        $this->assertCount(count($filePaths) + count($viewNames), $this->pdfManager->getFiles());

        $result = $this->pdfManager->buildFile($outputName, $data);

        // assert that the file has been created
        $this->assertStringEndsWith('/'.$outputName, $result);
        $this->assertTrue($this->fileSystem->exists($result));
        $this->assertEquals('application/pdf', $this->fileSystem->mimeType($result));
    }

    public function testBuildingWithNoFileNameThrowsError()
    {
        $this->expectException(InvalidArgumentException::class);

        $data = [
            'company_name' => $this->faker->company,
        ];

        // add the files from path names
        $this->pdfManager->addFile($this->pdfFiles[0]);

        $this->pdfManager->buildFile('', $data);
    }

    public function testCanBuildPdfWithNoFormData()
    {
        // create a unique name to test with
        $outputName = 'save-me-'.Str::uuid().'.pdf';

        // assert that the file doesn't already exist
        $this->assertFalse($this->fileSystem->exists($outputName));

        // add the files from path names
        $this->pdfManager->addFile($this->pdfFiles[0]);

        $result = $this->pdfManager->buildFile($outputName);

        // assert that the file has been created
        $this->assertStringEndsWith('/'.$outputName, $result);
        $this->assertTrue($this->fileSystem->exists($result));
        $this->assertEquals('application/pdf', $this->fileSystem->mimeType($result));
    }

    public function testBuildingWithNoFilesThrowsError()
    {
        $this->expectException(InvalidArgumentException::class);

        $this->pdfManager->buildFile('save-me-'.Str::uuid().'.pdf');
    }
}
