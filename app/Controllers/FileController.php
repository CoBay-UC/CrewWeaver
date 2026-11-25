<?php

declare(strict_types=1);

namespace CrewWeaver\Controllers;

use CrewWeaver\Authentication\AuthenticationService;
use CrewWeaver\Authorization\AuthorizationService;
use CrewWeaver\Core\Request;
use CrewWeaver\Core\Response;
use CrewWeaver\Models\AttachmentRepository;
use CrewWeaver\Storage\UploadStorage;
use Throwable;

class FileController
{
    /* ============================================================
     * PROTECTED VARIABLES
     * ============================================================ */
    protected AttachmentRepository $_attachments;
    protected UploadStorage $_storage;
    protected AuthenticationService $_authentication;
    protected AuthorizationService $_authorization;

    /* ============================================================
     * PUBLIC VARIABLES
     * ============================================================ */

    /* ============================================================
     * MAGIC METHODS
     * ============================================================ */
    public function __construct(AttachmentRepository $attachments, UploadStorage $storage, AuthenticationService $authentication, AuthorizationService $authorization)
    {
        $this->_attachments=$attachments;$this->_storage=$storage;$this->_authentication=$authentication;$this->_authorization=$authorization;
    }

    /* ============================================================
     * PROTECTED METHODS
     * ============================================================ */

    /* ============================================================
     * PUBLIC METHODS
     * ============================================================ */
    public function upload(Request $request): Response
    {
        $taskId=(int)$request->parameter('id');$user=$this->_authentication->currentUser();$scope=$user===null?null:$this->_authorization->taskScope($taskId,$user);if($scope===null){return Response::error('Task not found.',404);}if(!$this->_authorization->can('contribute_task',$user,$scope)){return Response::error('Forbidden.',403);}$file=$request->file('file');if($file===null){return Response::error('A file is required.',422,['file'=>['Select a file to upload.']]);}
        $stored=null;try{$stored=$this->_storage->store($file);$attachment=$this->_attachments->create($taskId,$user->id(),$stored,(string)$request->input('description',''));return Response::success('File uploaded successfully.',$attachment,201);}catch(Throwable $exception){if(is_array($stored)){$this->_storage->remove($stored['storage_name']);}return Response::error($exception->getMessage(),422);}
    }

    public function download(Request $request): Response
    {
        $attachment=$this->_attachments->find((int)$request->parameter('id'));if($attachment===null){return new Response('File not found.',404);}$path=$this->_storage->path($attachment['storage_name']);if(!is_file($path)){return new Response('Stored file not found.',404);}$body=file_get_contents($path);if($body===false){return new Response('Stored file is unavailable.',500);}$name=str_replace(['"','\r','\n'],['_','',''],$attachment['original_name']);return new Response($body,200,['Content-Type'=>$attachment['mime_type'],'Content-Length'=>(string)$attachment['size_bytes'],'Content-Disposition'=>'attachment; filename="'.$name.'"','Cache-Control'=>'private, no-store']);
    }

    public function delete(Request $request): Response
    {
        $attachment=$this->_attachments->find((int)$request->parameter('id'));if($attachment===null){return Response::error('File not found.',404);}$user=$this->_authentication->currentUser();$scope=$user===null?null:$this->_authorization->taskScope((int)$attachment['task_id'],$user);if($scope===null||!((int)$attachment['uploaded_by']===$user->id()||$this->_authorization->can('manage_project',$user,$scope))){return Response::error('Forbidden.',403);}$storageName=$this->_attachments->delete((int)$attachment['id']);if($storageName!==null){$this->_storage->remove($storageName);}return Response::success('File deleted.');
    }
}
