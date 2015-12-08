<?php

namespace MaterialLib\MaterialLibBundle\Controller;

use Topxia\Common\Paginator;
use Topxia\Common\ArrayToolkit;
use Symfony\Component\HttpFoundation\Request;
use Topxia\WebBundle\Controller\BaseController;

class MaterialLibController extends BaseController
{
    /**
     * Browse material lib. Use can switch between file types and search by keywords.
     * @param  Request                                    $request  HTTP request
     * @param  string                                     $type     file type (values: video|audio|document|image|ppt|other)
     * @param  string                                     $viewMode viewing mode (values: thumb|list)
     * @param  string                                     $source   source of the file (values: upload|shared)
     * @return \Symfony\Component\HttpFoundation\Response the HTTP response
     */
    public function indexAction(Request $request, $type = "all", $viewMode = "thumb", $source = "upload")
    {
        $currentUser = $this->getCurrentUser();

        if (!$currentUser->isTeacher() && !$currentUser->isAdmin()) {
            throw $this->createAccessDeniedException('您无权访问此页面');
        }

        $currentUserId = $currentUser['id'];

        $keyWord = $request->query->get('keyword') ?: "";

        $conditions          = array();
        $conditions['stats'] = 'ok';

        if ($type != 'all') {
            $conditions['type'] = $type;
        }

        if (!empty($keyWord)) {
            $conditions['filename'] = $keyWord;
        }

        $conditions['source']        = $source;
        $conditions['currentUserId'] = $currentUserId;

        $paginator = new Paginator($request, $this->getUploadFileService()->searchFilesCount($conditions), 20);

        $files = $this->getUploadFileService()->searchFiles($conditions, array('createdTime', 'DESC'), $paginator->getOffsetCount(), $paginator->getPerPageCount());

        $createdUsers = $this->getUserService()->findUsersByIds(ArrayToolkit::column($files, 'createdUserId'));

        //Return different views according to current viewing mode

        if ($viewMode == 'thumb') {
            $resultPage = 'MaterialLibBundle:MaterialLib:material-thumb-view.html.twig';
        } else {
            $resultPage = 'MaterialLibBundle:MaterialLib:material-list-view.html.twig';
        }

        $storageSetting = $this->getSettingService()->get("storage");

        return $this->render($resultPage, array(
            'currentUserId'  => $currentUserId,
            'type'           => $type,
            'files'          => $files,
            'createdUsers'   => $createdUsers,
            'paginator'      => $paginator,
            'storageSetting' => $storageSetting,
            'viewMode'       => $viewMode,
            'source'         => $source,
            'now'            => time()
        ));
    }

    public function deleteAction(Request $request, $id)
    {
        $file = $this->tryAccessFile($id);
        $this->getUploadFileService()->deleteFiles(array($id));
        return $this->createJsonResponse(true);
    }

    public function deletesAction(Request $request)
    {
        $ids = $request->request->get('ids');

        foreach ($ids as $id) {
            $file = $this->tryAccessFile($id);
            $this->getUploadFileService()->deleteFiles(array($id));
        }

        return $this->createJsonResponse(true);
    }

    /**
     * Get the users who is sharing material lib to current user. Inactive sharing contacts will not be included in the results.
     * @param  Request                                        $request HTTP request
     * @return \Symfony\Component\HttpFoundation\JsonResponse JSON response
     */
    public function findMySharingContactsAction(Request $request)
    {
        $user = $this->getCurrentUser();

        if (!$user->isTeacher() && !$user->isAdmin()) {
            throw $this->createAccessDeniedException('您无权访问此页面');
        }

        $mySharingContacts = $this->getUploadFileService()->findMySharingContacts($user['id']);

        return $this->createJsonResponse($mySharingContacts);
    }

    /**
     * Show the form to share my files to other users. The most recent 5 contacts will be displayed in the form by default.
     * @param  Request                                    $request HTTP request
     * @return \Symfony\Component\HttpFoundation\Response the for the share files
     */
    public function showShareFormAction(Request $request)
    {
        $currentUser = $this->getCurrentUser();

        if (!$currentUser->isTeacher() && !$currentUser->isAdmin()) {
            throw $this->createAccessDeniedException('您无权访问此页面');
        }

        $currentUserId = $currentUser['id'];

        $allTeachers = $this->getUserService()->searchUsers(array('roles' => 'ROLE_TEACHER', 'locked' => 0), array('nickname', 'ASC'), 0, 1000);

        return $this->render('MaterialLibBundle:MaterialLib:share-my-materials.html.twig', array(
            'allTeachers'   => $allTeachers,
            'currentUserId' => $currentUserId
        ));
    }

    /**
     * Show the sharing history list. The user can then deactivate the sharing records.
     * @param  Request                                    $request HTTP request
     * @return \Symfony\Component\HttpFoundation\Response HTTP response
     */
    public function showShareHistoryAction(Request $request)
    {
        $user = $this->getCurrentUser();

        if (!$user->isTeacher() && !$user->isAdmin()) {
            throw $this->createAccessDeniedException('您无权访问此页面');
        }

        $shareHistories = $this->getUploadFileService()->findShareHistory($user['id']);

        $targetUserIds = array();

        if (!empty($shareHistories)) {
            foreach ($shareHistories as $history) {
                array_push($targetUserIds, $history['targetUserId']);
            }

            $targetUsers = $this->getUserService()->findUsersByIds($targetUserIds);
        }

        return $this->render('MaterialLibBundle:MaterialLib:material-share-history.html.twig', array(
            'shareHistories' => $shareHistories,
            'targetUsers'    => isset($targetUsers) ? $targetUsers : array(),
            'source'         => 'myShareHistory'
        ));
    }

    /**
     * Save the sharing settings. If a previous sharing record exists, then update it. Otherwise create a new record.
     * @param  Request                                        $request HTTP request
     * @return \Symfony\Component\HttpFoundation\JsonResponse JSON response
     */
    public function saveShareAction(Request $request)
    {
        $currentUser = $this->getCurrentUser();

        if (!$currentUser->isTeacher() && !$currentUser->isAdmin()) {
            throw $this->createAccessDeniedException('您无权访问此页面');
        }

        $currentUserId = $currentUser['id'];
        $targetUserIds = $request->get('targetUserIds');

        if (!empty($targetUserIds)) {
            foreach ($targetUserIds as $targetUserId) {
                if ($targetUserId != $currentUserId) {
                    $shareHistory = $this->getUploadFileService()->findShareHistoryByUserId($currentUserId, $targetUserId);

                    if (isset($shareHistory)) {
                        $this->getUploadFileService()->updateShare($shareHistory['id']);
                    } else {
                        $this->getUploadFileService()->addShare($currentUserId, $targetUserId);
                    }

                    $targetUser   = $this->getUserService()->getUser($targetUserId);
                    $userUrl      = $this->generateUrl('user_show', array('id' => $currentUser['id']), true);
                    $toMyShareUrl = $this->generateUrl('material_lib_browsing', array('type' => 'all', 'viewMode' => 'thumb', 'source' => 'shared'));
                    $this->getNotificationService()->notify($targetUser['id'], 'default', "<a href='{$userUrl}' target='_blank'><strong>{$currentUser['nickname']}</strong></a>已将资料分享给你，<a href='{$toMyShareUrl}'>点击查看</a>");
                }
            }
        }

        return $this->createJsonResponse(true);
    }

    /**
     * Deactivate a sharing record. This will only set a flag in the database table.
     * The record will not be deleted. This is for the purpose to maintain a complete sharing history.
     * @param  Request                                        $request HTTP request
     * @return \Symfony\Component\HttpFoundation\JsonResponse JSON response
     */
    public function cancelShareAction(Request $request)
    {
        $currentUser = $this->getCurrentUser();

        if (!$currentUser->isTeacher() && !$currentUser->isAdmin()) {
            throw $this->createAccessDeniedException('您无权访问此页面');
        }

        $currentUserId = $currentUser['id'];
        $targetUserId  = $request->get('targetUserId');

        if (!empty($targetUserId)) {
            $this->getUploadFileService()->cancelShareFile($currentUserId, $targetUserId);

            $targetUser   = $this->getUserService()->getUser($targetUserId);
            $userUrl      = $this->generateUrl('user_show', array('id' => $currentUser['id']), true);
            $toMyShareUrl = $this->generateUrl('material_lib_browsing', array('type' => 'all', 'viewMode' => 'thumb', 'source' => 'shared'));
            $this->getNotificationService()->notify($targetUser['id'], 'default', "<a href='{$userUrl}' target='_blank'><strong>{$currentUser['nickname']}</strong></a>已取消分享资料给你，<a href='{$toMyShareUrl}'>点击查看</a>");
        }

        return $this->createJsonResponse(true);
    }

    public function previewAction(Request $request, $id)
    {
        $file = $this->tryAccessFile($id);

        return $this->render('MaterialLibBundle:MaterialLib:preview-modal.html.twig', array(
            'file' => $file
        ));
    }

    public function downloadAction(Request $request, $id)
    {
        $file = $this->tryAccessFile($id);
        return $this->forward('TopxiaWebBundle:FileWatch:download', array('file' => $file));
    }

    public function fileStatusAction(Request $request)
    {
        $currentUser = $this->getCurrentUser();

        if (!$currentUser->isTeacher() && !$currentUser->isAdmin()) {
            return $this->createJsonResponse(array());
        }

        $fileIds = $request->query->get('ids');

        if (empty($fileIds)) {
            return $this->createJsonResponse(array());
        }

        $fileIds = explode(',', $fileIds);

        $files = $this->getUploadFileService()->findFiles($fileIds);

        return $this->createJsonResponse(FileFilter::filters($files));
    }

    protected function tryAccessFile($fileId)
    {
        $file = $this->getUploadFileService()->getFile($fileId);

        if (empty($file)) {
            throw $this->createNotFoundException();
        }

        $user = $this->getCurrentUser();

        if ($user->isAdmin()) {
            return $file;
        }

        if (!$user->isTeacher()) {
            throw $this->createAccessDeniedException('您无权访问此文件！');
        }

        if ($file['createdUserId'] == $user['id']) {
            return $file;
        }

        $shares = $this->getUploadFileService()->findShareHistory($file['createdUserId']);

        foreach ($shares as $share) {
            if ($share['targetUserId'] == $user['id']) {
                return $file;
            }
        }

        throw $this->createAccessDeniedException('您无权访问此文件！');
    }

    protected function getSettingService()
    {
        return $this->getServiceKernel()->createService('System.SettingService');
    }

    protected function getUploadFileService()
    {
        return $this->getServiceKernel()->createService('File.UploadFileService2');
    }

    protected function getUserService()
    {
        return $this->getServiceKernel()->createService('User.UserService');
    }

    protected function getNotificationService()
    {
        return $this->getServiceKernel()->createService('User.NotificationService');
    }
}

class FileFilter
{
    public static function filters($files)
    {
        $filterResult = array();

        if (empty($files)) {
            return $filterResult;
        }

        foreach ($files as $index => $file) {
            array_push($filterResult, array('id' => $file['id'], 'convertStatus' => $file['convertStatus']));
        }

        return $filterResult;
    }
}
