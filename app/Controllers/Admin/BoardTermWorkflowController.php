<?php

declare(strict_types=1);
namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Services\{AdminCrudService, BoardTermService};
use InvalidArgumentException;

/** Dedicated routes for preview, copy, approval and publication. Actors come from the session. */
class BoardTermWorkflowController extends BoardStaffController
{
    public function apiTermsIndex(): void { AdminCrudService::respond($this->response,fn()=>['data'=>BoardTermService::getAllTerms()]); }
    public function apiTermShow(string $id): void { AdminCrudService::respond($this->response,fn()=>['data'=>BoardTermService::getTerm($this->id($id))]); }
    public function apiTermPreview(string $id): void { AdminCrudService::respond($this->response,fn()=>['data'=>BoardTermService::getTermPreview($this->id($id))]); }
    public function apiTermStore(): void { AdminCrudService::respond($this->response,function (): array {
        $data=$this->request->all();$data['term_number']=$this->id((string)($data['term_number'] ?? ''));
        $id=BoardTermService::createTerm($data);return ['data'=>BoardTermService::getTerm($id)];
    },201); }
    public function apiTermCopy(string $id): void { AdminCrudService::respond($this->response,function () use ($id): array {
        BoardTermService::copyTerm($this->id($id),$this->target());return [];
    }); }
    public function apiTermUpdate(string $id): void { AdminCrudService::respond($this->response,function () use ($id): array {
        $id=$this->id($id);BoardTermService::updateTerm($id,$this->request->all());return ['data'=>BoardTermService::getTerm($id)];
    }); }
    public function apiTermSaveMembers(string $id): void { AdminCrudService::respond($this->response,function () use ($id): array {
        $id=$this->id($id);BoardTermService::saveTermMembers($id,(array)$this->request->input('members',[]),(string)$this->request->input('category','director'));return ['data'=>BoardTermService::getTerm($id)];
    }); }
    public function apiTermSaveGroups(string $id): void { AdminCrudService::respond($this->response,function () use ($id): array {
        $id=$this->id($id);BoardTermService::saveTermGroups($id,(array)$this->request->input('groups',[]));return ['data'=>BoardTermService::getTerm($id)];
    }); }
    public function apiReconcileTerm38(): void { AdminCrudService::respond($this->response,function (): array {
        $result=BoardTermService::reconcileTerm38((bool)$this->request->input('force',false));return ['data'=>$result,'message'=>$result['message']];
    }); }
    private function id(string $id): int
    {
        if (!preg_match('/^[1-9][0-9]*$/D',$id) || filter_var($id,FILTER_VALIDATE_INT) === false) throw new InvalidArgumentException('Invalid term ID');
        return (int)$id;
    }
    private function target(): int
    {
        return $this->id((string)$this->request->input('target_term_number',$this->request->input('target_term',39)));
    }
    private function options(): array
    {
        return ['transfer_groups'=>(bool)$this->request->input('transfer_groups',true),
            'term_name'=>$this->request->input('term_name'),
            'version'=>(string)$this->request->input('version','')];
    }
    public function apiImportTerm38Preview(): void
    {
        AdminCrudService::respond($this->response,fn()=>['data'=>BoardTermService::getTerm38ImportPreview()]);
    }
    public function apiImportTerm38(): void
    {
        AdminCrudService::respond($this->response,function (): array {
            $result=BoardTermService::importTerm38([
                'preserve_custom_photos'=>$this->request->input('preserve_custom_photos',true),
                'preserve_admin_edits'=>$this->request->input('preserve_admin_edits',true)]);
            return ['data'=>$result,'message'=>$result['message']];
        });
    }
    public function apiSelectionCandidates(string $id): void
    {
        AdminCrudService::respond($this->response,fn()=>['data'=>BoardTermService::getSelectionCandidates($this->id($id),$this->target())]);
    }
    public function apiSelectiveCopyPreview(string $id): void
    {
        AdminCrudService::respond($this->response,fn()=>['data'=>BoardTermService::getSelectiveTransferPreview($this->id($id),$this->target(),(array)$this->request->input('selections',[]),$this->options())]);
    }
    public function apiSelectiveCopy(string $id): void
    {
        AdminCrudService::respond($this->response,function () use ($id): array {
            $result=BoardTermService::selectiveTransfer($this->id($id),$this->target(),(array)$this->request->input('selections',[]),$this->options());
            return ['data'=>$result,'message'=>$result['message']];
        });
    }
    public function apiTermApprove(string $id): void
    {
        AdminCrudService::respond($this->response,function () use ($id): array {
            BoardTermService::approveTerm($this->id($id));
            return ['data'=>BoardTermService::getTerm($this->id($id)),'message'=>'Draft approved. Publication requires a separate action.'];
        });
    }
    public function apiTermPublish(string $id): void
    {
        AdminCrudService::respond($this->response,function () use ($id): array {
            BoardTermService::publishTerm($this->id($id));
            return ['data'=>BoardTermService::getTerm($this->id($id)),'message'=>'Term published'];
        });
    }
    public function apiTermSchedule(string $id): void
    {
        AdminCrudService::respond($this->response,function () use ($id): array {
            BoardTermService::scheduleTerm($this->id($id),(string)$this->request->input('publish_at',''));
            return [];
        });
    }
}
