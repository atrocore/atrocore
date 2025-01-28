<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

declare(strict_types=1);

namespace Atro\Repositories;

use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Exceptions\NotFound;
use Atro\Core\Exceptions\NotUnique;
use Atro\Core\FileStorage\FileStorageInterface;
use Atro\Core\FileStorage\HasBasketInterface;
use Atro\Core\FileStorage\LocalFileStorageInterface;
use Atro\Core\FileStorage\LocalStorage;
use Atro\Core\FileValidator;
use Atro\Core\Utils\FileManager;
use Atro\Core\Utils\PDFLib;
use Atro\Entities\File as FileEntity;
use Atro\Core\Templates\Repositories\Base;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\ParameterType;
use Espo\Core\FilePathBuilder;
use Espo\ORM\Entity;
use Imagick;

class File extends Base
{
    protected function beforeSave(Entity $entity, array $options = [])
    {
        parent::beforeSave($entity, $options);

        $this->prepareThumbnailsPath($entity);

        // validate via type
        $this->validateByType($entity);

        //validate via allow types of relation
        $this->validateByTypeUsingAllowFileTypes($entity);

        // validate file name
        $this->validateItemName($entity);

        if (!$entity->isNew()) {
            if ($entity->isAttributeChanged('storageId')) {
                throw (new BadRequest($this->getInjection('language')->translate('fileStorageCannotBeChanged', 'exceptions', 'File')))->setDataItem('skipOnScan', true);
            }

            if ($entity->isAttributeChanged('folderId')) {
                $storageId = $this->getEntityManager()->getRepository('Folder')->getFolderStorage($entity->get('folderId') ?? '')->get('id');
                if ($storageId !== $entity->get('storageId')) {
                    throw new BadRequest($this->getInjection('language')->translate('itemCannotBeMovedToAnotherStorage', 'exceptions', 'Storage'));
                }
                if (!$this->getStorage($entity)->moveFile($entity)) {
                    throw new BadRequest($this->getInjection('language')->translate('fileMoveFailed', 'exceptions', 'File'));
                }
            }

            if (!empty($entity->_input) && !empty($entity->_input->reupload)) {
                if ($entity->isAttributeChanged('folderId')) {
                    throw new BadRequest($this->getInjection('language')->translate('fileFolderCannotBeChanged', 'exceptions', 'File'));
                }
                // recreate origin file
                if (!$this->getStorage($entity)->reupload($entity)) {
                    throw new BadRequest($this->getInjection('language')->translate('fileCreateFailed', 'exceptions', 'File'));
                }
            } else {
                if ($entity->isAttributeChanged('name') || $entity->isAttributeChanged('folderId')) {
                    $this->updateItem($entity);
                }

                if ($entity->isAttributeChanged('name') && empty($options['scanning'])) {
                    $this->rename($entity);
                }
            }
        } else {
            // assign the file type automatically
            $this->assignTheFileTypeAutomatically($entity);

            $this->createItem($entity);

            // create origin file
            if (empty($options['scanning']) && !$this->getStorage($entity)->createFile($entity)) {
                throw new BadRequest($this->getInjection('language')->translate('fileCreateFailed', 'exceptions', 'File'));
            }

            if($this->getConfig()->get('automaticFileExtensionCorrection')){
                $this->automaticallyCorrectExtension($entity);
            }

            $this->addDimensions($entity);
        }
    }

    public function prepareThumbnailsPath(FileEntity $file): void
    {
        if (empty($file->get('thumbnailsPath'))) {
            if (!empty($file->get('path'))) {
                $file->set('thumbnailsPath', $file->get('path'));
            } else {
                $thumbnailsDirPath = trim($this->getConfig()->get('thumbnailsPath', 'upload/thumbnails'), '/');
                $file->set('thumbnailsPath', $this->getPathBuilder()->createPath($thumbnailsDirPath . DIRECTORY_SEPARATOR));
            }
        }
    }

    public function save(Entity $entity, array $options = [])
    {
        $inTransaction = false;

        if (empty($this->getMemoryStorage()->get('exportJobId')) && !$this->getPDO()->inTransaction()) {
            $this->getPDO()->beginTransaction();
            $inTransaction = true;
        }

        try {
            $res = parent::save($entity, $options);
        } catch (\Throwable $e) {
            if ($inTransaction) {
                $this->getPDO()->rollBack();
            }
            throw $e;
        }

        if ($inTransaction) {
            $this->getPDO()->commit();
        }

        return $res;
    }

    protected function deleteEntity(Entity $entity): bool
    {
        $inTransaction = false;

        if (empty($this->getMemoryStorage()->get('exportJobId')) && !$this->getPDO()->inTransaction()) {
            $this->getPDO()->beginTransaction();
            $inTransaction = true;
        }

        try {
            $res = parent::deleteEntity($entity);
        } catch (\Throwable $e) {
            if ($inTransaction) {
                $this->getPDO()->rollBack();
            }
            throw $e;
        }

        if ($inTransaction) {
            $this->getPDO()->commit();
        }

        return $res;
    }

    protected function afterRemove(Entity $entity, array $options = [])
    {
        parent::afterRemove($entity, $options);

        $this->removeItem($entity);

        $storage = $this->getStorage($entity);
        if ($storage instanceof HasBasketInterface) {
            if (!$storage->deleteFile($entity)) {
                throw new BadRequest($this->getInjection('language')->translate('fileDeleteFailed', 'exceptions', 'File'));
            }
        } else {
            $this->deleteFromDb($entity->get('id'));
        }
    }

    protected function beforeRestore($id)
    {
        parent::beforeRestore($id);

        $rec = $this->getConnection()->createQueryBuilder()
            ->select('f.id, f.storage_id, st.folder_id as storage_folder_id, f.folder_id as file_folder_id, f1.id as folder_id, f1.deleted as folder_deleted')
            ->from('file', 'f')
            ->leftJoin('f', 'folder', 'f1', 'f.folder_id=f1.id')
            ->leftJoin('f', 'storage', 'st', 'f.storage_id=st.id')
            ->where('f.id=:id')
            ->setParameter('id', $id)
            ->fetchAssociative();

        if (!empty($rec['folder_id']) && !empty($rec['folder_deleted'])) {
            try {
                // restore folder
                $this->getInjection('container')->get('serviceFactory')->create('Folder')->restoreEntity($rec['folder_id']);
            } catch (\Throwable $e) {
                $this->changeFileFolderToStorageRoot($id);
            }
        }

        if (empty($rec['folder_id']) && !empty($rec['file_folder_id'])) {
            $this->changeFileFolderToStorageRoot($id);
        }
    }

    protected function changeFileFolderToStorageRoot(string $fileId): void
    {
        $qb = $this->getConnection()->createQueryBuilder()
            ->update('file')
            ->set('folder_id', ':storageFolder')
            ->where('id=:id')
            ->setParameter('id', $fileId);
        if (empty($rec['storage_folder_id'])) {
            $qb->setParameter('storageFolder', null, ParameterType::NULL);
        } else {
            $qb->setParameter('storageFolder', $rec['storage_folder_id']);
        }
        $qb->executeQuery();
    }

    protected function afterRestore($entity)
    {
        $this->getStorage($entity)->restoreFile($entity);
    }

    public function deleteFromDb(string $id): bool
    {
        /** @var \Atro\Entities\File $file */
        $file = $this->getMapper()->selectById($this->entityFactory->create($this->entityType), $id, ['withDeleted' => true]);
        if (!empty($file)) {
            $this->getStorage($file)->deleteFilePermanently($file);
        }

        return parent::deleteFromDb($id);
    }

    public function rename(FileEntity $file): void
    {
        if ($this->isExtensionChanged($file)) {
            throw new BadRequest($this->getInjection('language')->translate('fileExtensionCannotBeChanged', 'exceptions', 'File'));
        }

        if (!$this->isNameValid($file)) {
            throw new BadRequest(
                sprintf($this->getInjection('language')->translate('fileNameNotValidByUserRegex', 'exceptions', 'File'), $this->getConfig()->get('fileNameRegexPattern'))
            );
        }

        if (!$this->getStorage($file)->renameFile($file)) {
            throw new BadRequest($this->getInjection('language')->translate('fileRenameFailed', 'exceptions', 'File'));
        }
    }

    public function validateByType(FileEntity $file): void
    {
        if (!empty($file->get('typeId'))) {
            $fileType = $this->getEntityManager()->getRepository('FileType')->get($file->get('typeId'));
            $this->getFileValidator()->validateFile($fileType, $file, true);
        }
    }

    public function isNameValid(FileEntity $file): bool
    {
        $fileNameRegexPattern = $this->getConfig()->get('fileNameRegexPattern');
        if (!empty($fileNameRegexPattern)) {
            $nameWithoutExt = explode('.', (string)$file->get('name'));
            array_pop($nameWithoutExt);
            $nameWithoutExt = implode('.', $nameWithoutExt);
            return preg_match($fileNameRegexPattern, $nameWithoutExt);
        }

        return true;
    }

    public function isExtensionChanged(FileEntity $file): bool
    {
        $fetchedParts = explode('.', (string)$file->getFetched('name'));
        $fetchedExt = array_pop($fetchedParts);

        $parts = explode('.', (string)$file->get('name'));
        $ext = array_pop($parts);

        return $fetchedExt !== $ext;
    }

    public function getContents(FileEntity $file): string
    {
        if ($file->isNew()) {
            $input = $file->_input ?? new \stdClass();
            if (property_exists($input, 'fileContents')) {
                return LocalStorage::parseInputFileContent((string)$input->fileContents);
            }
        }
        return $this->getStorage($file)->getContents($file);
    }

    public function getFilePath(FileEntity $file): string
    {
        $fileStorage = $this->getStorage($file);

        if ($fileStorage instanceof LocalFileStorageInterface) {
            return $fileStorage->getLocalPath($file);
        }

        return $fileStorage->getUrl($file);
    }

    public function getPathsData(FileEntity $file): array
    {
        return [
            'download'   => $this->getDownloadUrl($file),
            'thumbnails' => [
                'small'  => $this->getSmallThumbnailUrl($file),
                'medium' => $this->getMediumThumbnailUrl($file),
                'large'  => $this->getLargeThumbnailUrl($file)
            ],
        ];
    }

    public function createItem(Entity $entity): void
    {
        $storage = $entity->getStorage();
        if (empty($storage)) {
            return;
        }

        if ($storage->get('type') === 'local' && empty($storage->get('syncFolders'))) {
            return;
        }

        $fileFolderLinker = $this->getEntityManager()->getRepository('FileFolderLinker')->get();
        $fileFolderLinker->set([
            'name'     => $entity->get('name'),
            'parentId' => $entity->get('folderId') ?? '',
            'fileId'   => $entity->get('id')
        ]);

        try {
            $this->getEntityManager()->saveEntity($fileFolderLinker);
        } catch (UniqueConstraintViolationException $e) {
            throw new NotUnique($this->getInjection('language')->translate('suchItemNameCannotBeUsedHere', 'exceptions'));
        }
    }

    public function updateItem(Entity $entity): void
    {
        $storage = $entity->getStorage();
        if (empty($storage)) {
            return;
        }

        if ($storage->get('type') === 'local' && empty($storage->get('syncFolders'))) {
            return;
        }

        $fileFolderLinker = $this->getEntityManager()->getRepository('FileFolderLinker')
            ->where(['fileId' => $entity->get('id')])
            ->findOne();

        if (empty($fileFolderLinker)) {
            return;
        }

        $fileFolderLinker->set('name', $entity->get('name'));
        $fileFolderLinker->set('parentId', $entity->get('folderId') ?? '');

        try {
            $this->getEntityManager()->saveEntity($fileFolderLinker);
        } catch (UniqueConstraintViolationException $e) {
            throw new NotUnique($this->getInjection('language')->translate('suchItemNameCannotBeUsedHere', 'exceptions'));
        }
    }

    public function removeItem(Entity $entity): void
    {
        $storage = $entity->getStorage();
        if (empty($storage)) {
            return;
        }

        if ($storage->get('type') === 'local' && empty($storage->get('syncFolders'))) {
            return;
        }

        $fileFolderLinker = $this->getEntityManager()->getRepository('FileFolderLinker')
            ->where(['fileId' => $entity->get('id')])
            ->findOne();

        if (empty($fileFolderLinker)) {
            return;
        }

        $this->getEntityManager()->removeEntity($fileFolderLinker);
    }

    public function validateItemName(FileEntity $file): void
    {
        if ($file->isNew() || $file->isAttributeChanged('name') || $file->isAttributeChanged('folderId')) {
            $qb = $this->getConnection()->createQueryBuilder()
                ->select('*')
                ->from('file_folder_linker')
                ->where('name=:name')
                ->andWhere('parent_id=:parentId')
                ->setParameter('name', $file->get('name'))
                ->setParameter('parentId', $file->get('folderId') ?? '');

            if (!$file->isNew()) {
                $qb->andWhere('id!=:id')->setParameter('id', $file->get('id'));
            }

            if (!empty($qb->fetchAssociative())) {
                throw new NotUnique($this->getInjection('language')->translate('suchItemNameCannotBeUsedHere', 'exceptions'));
            }
        }
    }

    public function getDownloadUrl(FileEntity $file): ?string
    {
        if (empty($file->get('storageId'))) {
            return null;
        }

        try {
            $url = $this->getStorage($file)->getUrl($file);
        } catch (BadRequest|NotFound $e) {
            $url = null;
        }

        return $url;
    }

    public function getSmallThumbnailUrl(FileEntity $file): ?string
    {
        if (empty($file->get('storageId'))) {
            return null;
        }

        try {
            $url = $this->getStorage($file)->getThumbnail($file, 'small');
        } catch (BadRequest|NotFound $e) {
            $url = null;
        }

        return $url;
    }

    public function getMediumThumbnailUrl(FileEntity $file): ?string
    {
        if (empty($file->get('storageId'))) {
            return null;
        }

        try {
            $url = $this->getStorage($file)->getThumbnail($file, 'medium');
        } catch (BadRequest|NotFound $e) {
            $url = null;
        }

        return $url;
    }

    public function addDimensions(FileEntity $file):void
    {
        if(!$file->isImage() && !$file->isPdf()) {
            return;
        }

       if($this->getStorage($file) instanceof LocalFileStorageInterface) {
           $filePath = $this->getFilePath($file);
       }else{
           $filePath = LocalStorage::TMP_DIR . DIRECTORY_SEPARATOR . $file->get('name');
           if(!is_dir(LocalStorage::TMP_DIR)){
               @mkdir(LocalStorage::TMP_DIR, 0777, true);
           }
           $this->getFileManager()->putContents($filePath, $file->getContents());
           $isTempFile = true;
       }

       if($file->isImage()) {
           $this->addDimensionFromImage($file, $filePath);
       }

       if($file->isPdf()) {
           if(!is_dir(LocalStorage::TMP_DIR)){
               @mkdir(LocalStorage::TMP_DIR, 0777, true);
           }

           $firstPagePath = LocalStorage::TMP_DIR . '/page-1.png';
           $pdflib = new PDFLib($this->getConfig());
           $pdflib->setPdfPath($filePath);
           $pdflib->setOutputPath(LocalStorage::TMP_DIR);
           $pdflib->setImageFormat(PDFLib::$IMAGE_FORMAT_PNG);
           $pdflib->setPageRange(1, 1);
           $pdflib->setFilePrefix('page-');
           $pdflib->convert();
           $this->addDimensionFromImage($file, $firstPagePath);
           $this->getFileManager()->removeFile([$firstPagePath]);
       }

       if(!empty($isTempFile)) {
           $this->getFileManager()->removeFile([$filePath]);
       }
    }

    public function getLargeThumbnailUrl(FileEntity $file): ?string
    {
        if (empty($file->get('storageId'))) {
            return null;
        }

        try {
            $url = $this->getStorage($file)->getThumbnail($file, 'large');
        } catch (BadRequest|NotFound $e) {
            $url = null;
        }

        return $url;
    }

    protected function addDimensionFromImage(FileEntity $file, string $imagePath): void
    {
        try {
            $image = new Imagick($imagePath);

            $file->set('width', $image->getImageWidth());
            $file->set('widthUnitId', 'pixel');
            $file->set('height', $image->getImageHeight());
            $file->set('heightUnitId', 'pixel');

            $colorspaceName = match ($image->getImageColorspace()) {
                Imagick::COLORSPACE_RGB => 'RGB',
                Imagick::COLORSPACE_GRAY => 'Grayscale',
                Imagick::COLORSPACE_TRANSPARENT => 'Transparent',
                Imagick::COLORSPACE_OHTA => 'OHTA',
                Imagick::COLORSPACE_LAB => 'LAB',
                Imagick::COLORSPACE_XYZ => 'XYZ',
                Imagick::COLORSPACE_YCBCR => 'YCbCr',
                Imagick::COLORSPACE_YCC => 'YCC',
                Imagick::COLORSPACE_YIQ => 'YIQ',
                Imagick::COLORSPACE_YPBPR => 'YPbPr',
                Imagick::COLORSPACE_YUV => 'YUV',
                Imagick::COLORSPACE_CMYK => 'CMYK',
                Imagick::COLORSPACE_SRGB => 'sRGB',
                Imagick::COLORSPACE_HSB => 'HSB',
                Imagick::COLORSPACE_HSL => 'HSL',
                Imagick::COLORSPACE_HWB => 'HWB',
                Imagick::COLORSPACE_REC601LUMA => 'Rec601Luma',
                Imagick::COLORSPACE_REC709LUMA => 'Rec709Luma',
                Imagick::COLORSPACE_LOG => 'Log',
                default => 'Unknown'
            };
            $file->set('colorSpace', $colorspaceName);
            $image->clear();
        } catch (\ImagickException $e) {
            $GLOBALS['log']->error('[Unable to get image dimensions] : ' . $e->getMessage());
        }
    }

    protected function validateByTypeUsingAllowFileTypes(FileEntity $file): void
    {
        if (!empty($file->get('typeId')) || empty($file->_input) || empty($file->_input->_uploadForEntityData->scope) || empty($file->_input->_uploadForEntityData->link)) {
            return;
        }

        $allowFileTypesIds = $this->getMetadata()->get(['entityDefs', $file->_input->_uploadForEntityData->scope, 'fields', $file->_input->_uploadForEntityData->link, 'fileTypes']);

        if(empty($allowFileTypesIds)) {
            return;
        }

        $isValid = false;
        $message = "";
        foreach ($allowFileTypesIds as $typeId){
            try{
                $fileType = $this->getEntityManager()->getRepository('FileType')->get($typeId);
                $this->getFileValidator()->validateFile($fileType, $file, true);
                $isValid = true;
                break;
            }catch (\Throwable $e){
                if(!empty($message)){

                    $message .= ' | ' ;
                }
                $message .= $e->getMessage();
            }
        }
        if(!$isValid) {
            throw new BadRequest($message);
        }
    }

    protected function assignTheFileTypeAutomatically(Entity $entity): void
    {
        if (empty($entity->get('typeId'))) {
            $fileTypes = $this->getEntityManager()->getRepository('FileType')
                ->where(['assignAutomatically' => true])
                ->order('priority', 'DESC')
                ->find();
            foreach ($fileTypes as $fileType) {
                if ($this->getFileValidator()->validateFile($fileType, $entity)) {
                    $entity->set('typeId', $fileType->get('id'));
                    break;
                }
            }
        }
    }

    protected function automaticallyCorrectExtension(Entity $entity): void
    {
        if(empty($entity->_input->fromApi)) {
            return;
        }

        if(!empty($entity->_input->shouldAvoidAutomaticalExtensionUpdate )) {
            return;
        }

        $mimes = $this->getMetadata()->get(['app', 'mimeTypeToExtensions'], []);
        if(empty($mimes[$entity->get('mimeType')]) or !is_array($mimes[$entity->get('mimeType')])) {
            return;
        }

        $realExtension = $mimes[$entity->get('mimeType')];
        $nameParts = explode('.', $entity->get('name'));

        if(count($nameParts) === 1){
            $nameParts[2] = $realExtension[0];
        }

        if(!in_array($nameParts[count($nameParts) - 1], $realExtension)) {
            $nameParts[count($nameParts) - 1] = $realExtension[0];
            $entity->set('extensionCorrected', true);
        }

        $entity->set('name', join('.', $nameParts));

        if(empty($entity->_input->typeId)){
            $entity->set('typeId', null);
            $this->assignTheFileTypeAutomatically($entity);
        }

        $this->getStorage($entity)->reupload($entity);
    }

    public function getStorage(FileEntity $file): FileStorageInterface
    {
        return $this->getEntityManager()->getRepository('Storage')->getFileStorage($file->get('storageId'));
    }

    protected function getPathBuilder(): FilePathBuilder
    {
        return $this->getInjection('container')->get('filePathBuilder');
    }

    protected function getFileValidator(): FileValidator
    {
        return $this->getInjection('container')->get(FileValidator::class);
    }

    protected function getFileManager(): FileManager
    {
        return $this->getInjection('fileManager');
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('container');
        $this->addDependency('language');
        $this->addDependency('fileValidator');
        $this->addDependency('fileManager');
    }
}
