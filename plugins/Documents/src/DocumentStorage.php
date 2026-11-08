<?php
namespace Plugins\Documents;
use Companyware\Core\Framework\Data\EntityQuery;
use Companyware\Core\Framework\Plugin\Contract\PluginConfigurationInterface;
use Companyware\Core\Framework\File\FileNameNormalizerInterface;
use Plugins\Documents\Domain\Document;
use Plugins\Documents\Domain\DocumentRepositoryInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
final class DocumentStorage
{
    private string $directory;
    public function __construct(private readonly DocumentRepositoryInterface $documents,private readonly PluginConfigurationInterface $configuration,private readonly FileNameNormalizerInterface $fileNames,string $projectDir){$this->directory=$projectDir.'/var/documents';}
    public function all(): array { return array_map($this->toArray(...),$this->documents->search(new EntityQuery(limit:500))); }
    public function search(EntityQuery $query): array { return array_map($this->toArray(...),$this->documents->search($query)); }
    public function count(EntityQuery $query): int { return $this->documents->count($query); }
    public function find(int $id): ?array { $document=$this->documents->find((string)$id);return $document instanceof Document?$this->toArray($document):null; }
    public function findScoped(int $id,array $filters):?array{$items=$this->documents->search(new EntityQuery(['id'=>$id]+$filters,limit:1));return isset($items[0])?$this->toArray($items[0]):null;}
    public function add(UploadedFile $file,string $title,int $adminId): array
    {
        if(!$file->isValid()||$file->getSize()>$this->maxUploadMb()*1_000_000) throw new \RuntimeException('documents.error.invalid');
        $allowed=['application/pdf','text/plain','image/jpeg','image/png','application/msword','application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
        $mime=$file->getMimeType()?:'application/octet-stream'; if(!in_array($mime,$allowed,true)) throw new \RuntimeException('documents.error.type');
        $original=$this->fileNames->normalize($file->getClientOriginalName());
        (new Filesystem())->mkdir($this->directory,0750); $stored=bin2hex(random_bytes(24)); $file->move($this->directory,$stored);
        try{return $this->toArray($this->documents->create($title?:$original,$original,$stored,$mime,(int)filesize($this->directory.'/'.$stored),$adminId));}catch(\Throwable $e){@unlink($this->directory.'/'.$stored);throw $e;}
    }
    public function path(array $document): string { return $this->directory.'/'.$document['stored_name']; }
    public function maxUploadMb(): int { return max(1,min(100,(int)$this->configuration->get('documents','max_upload_mb',15))); }
    public function delete(int $id): void { $entity=$this->documents->find((string)$id);if(!$entity instanceof Document)return;$document=$this->toArray($entity);$this->documents->remove($entity);@unlink($this->directory.'/'.$document['stored_name']); }
    public function deleteScoped(int $id,array $filters):void{$items=$this->documents->search(new EntityQuery(['id'=>$id]+$filters,limit:1));$entity=$items[0]??null;if(!$entity instanceof Document)return;$document=$this->toArray($entity);$this->documents->remove($entity);@unlink($this->directory.'/'.$document['stored_name']);}
    private function toArray(Document $document): array{return ['id'=>(int)$document->id(),'title'=>$document->title,'original_name'=>$document->originalName,'stored_name'=>$document->storedName,'mime_type'=>$document->mimeType,'file_size'=>$document->fileSize,'uploaded_by'=>$document->uploadedBy,'created_at'=>$document->createdAt()->format('Y-m-d H:i:s'),'updated_at'=>$document->updatedAt()->format('Y-m-d H:i:s'),'version'=>$document->version()];}
}
