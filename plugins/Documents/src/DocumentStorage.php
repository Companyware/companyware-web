<?php
namespace Plugins\Documents;
use App\Infrastructure\Database;
use Companyware\Core\Framework\Plugin\Contract\PluginConfigurationInterface;
use Companyware\Core\Framework\File\FileNameNormalizerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
final class DocumentStorage
{
    private string $directory;
    public function __construct(private readonly Database $database,private readonly PluginConfigurationInterface $configuration,private readonly FileNameNormalizerInterface $fileNames,string $projectDir){$this->directory=$projectDir.'/var/documents';}
    public function all(): array { return $this->database->connection()->query('SELECT * FROM document_file ORDER BY created_at DESC')->fetchAll(); }
    public function find(int $id): ?array { $s=$this->database->connection()->prepare('SELECT * FROM document_file WHERE id=:id');$s->execute(['id'=>$id]);return $s->fetch()?:null; }
    public function add(UploadedFile $file,string $title,int $adminId): void
    {
        if(!$file->isValid()||$file->getSize()>$this->maxUploadMb()*1_000_000) throw new \RuntimeException('documents.error.invalid');
        $allowed=['application/pdf','text/plain','image/jpeg','image/png','application/msword','application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
        $mime=$file->getMimeType()?:'application/octet-stream'; if(!in_array($mime,$allowed,true)) throw new \RuntimeException('documents.error.type');
        $original=$this->fileNames->normalize($file->getClientOriginalName());
        (new Filesystem())->mkdir($this->directory,0750); $stored=bin2hex(random_bytes(24)); $file->move($this->directory,$stored);
        $s=$this->database->connection()->prepare('INSERT INTO document_file (title,original_name,stored_name,mime_type,file_size,uploaded_by) VALUES (:title,:original,:stored,:mime,:size,:user)');
        try{$s->execute(['title'=>$title?:$original,'original'=>$original,'stored'=>$stored,'mime'=>$mime,'size'=>filesize($this->directory.'/'.$stored),'user'=>$adminId]);}catch(\Throwable $e){@unlink($this->directory.'/'.$stored);throw $e;}
    }
    public function path(array $document): string { return $this->directory.'/'.$document['stored_name']; }
    public function maxUploadMb(): int { return max(1,min(100,(int)$this->configuration->get('documents','max_upload_mb',15))); }
    public function delete(int $id): void { $document=$this->find($id);if(!$document)return;$s=$this->database->connection()->prepare('DELETE FROM document_file WHERE id=:id');$s->execute(['id'=>$id]);@unlink($this->path($document)); }
}
