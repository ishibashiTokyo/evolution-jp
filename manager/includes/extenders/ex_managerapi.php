<?php
$this->manager= new ManagerAPI;

/*
* MODx Manager API Class
* Written by Raymond Irving 2005
*
*/

global $_PAGE; // page view state object. Usage $_PAGE['vs']['propertyname'] = $value;

// Content manager wrapper class
class ManagerAPI {

    public $action; // action directive

    function __construct(){
        global $action;
        $this->action = $action; // set action directive
        if(isset($_POST['token'])||isset($_GET['token'])) {
            $rs = $this->checkToken();
            if(!$rs) exit('unvalid token');
        }
    }

    function initPageViewState($id=0)
    {
        global $modx, $_PAGE;

        if($modx->session_var('mgrPageViewSID', '') != $this->action) {
            $_SESSION['mgrPageViewSDATA'] = array(); // new view state
            $_SESSION['mgrPageViewSID']   = ($id > 0) ? $id : $this->action; // set id
        }
        $_PAGE['vs'] = &$_SESSION['mgrPageViewSDATA']; // restore viewstate
    }

    // save page view state - not really necessary,
    function savePageViewState($id=0)
    {
        global $_PAGE;
        $_SESSION['mgrPageViewSDATA'] = $_PAGE['vs'];
        $_SESSION['mgrPageViewSID']   = ($id > 0) ? $id : $this->action;
    }

    // check for saved form
    function hasFormValues() {
        global $modx;
        if(isset($_SESSION['mgrFormValueId']) && $modx->session_var('mgrFormValues'))
        {
            if($this->action==$_SESSION['mgrFormValueId'] && is_array($_SESSION['mgrFormValues']))
            {
                return true;
            }

            $this->clearSavedFormValues();
        }
        return false;
    }
    // saved form post from $_POST
    function saveFormValues($id=0)
    {
        if(!$_POST) return false;
        $_SESSION['mgrFormValues']  = $_POST;
        $_SESSION['mgrFormValueId'] = ($id > 0) ? $id : $this->action;
        return true;
    }
    // load saved form values into $_POST
    function loadFormValues()
    {
        if($this->hasFormValues())
        {
            $p = $_SESSION['mgrFormValues'];
            foreach($p as $k=>$v)
            {
                $_POST[$k]=$v;
            }
            $this->clearSavedFormValues();
            return $_POST;
        }

        return false;
    }
    // clear form post
    function clearSavedFormValues()
    {
        unset($_SESSION['mgrFormValues'], $_SESSION['mgrFormValueId']);
    }

    function get_alias_from_title($id=0,$pagetitle='')
    {
        global $modx;
        if($id==='') {
            $id = 0;
        }

        $pagetitle = trim($pagetitle);
        if($pagetitle!=='') {
            $alias = strtolower($modx->stripAlias($pagetitle));
        }

        if($modx->config['allow_duplicate_alias']) {
            return '';
        }

        $rs = db()->select(
            'id'
            , '[+prefix+]site_content'
            , sprintf("id<>'%s' AND alias='%s'", $id, $alias)
        );
        if (db()->getRecordCount($rs)) {
            $c = 2;
            $_ = $alias;
            while (0 < db()->getRecordCount(
                    db()->select('id',
                        '[+prefix+]site_content',
                        sprintf("id!='%s' AND alias='%s'", $id, $_)
                    ))
            ) {
                $_ = $alias;
                $_ .= '_' . $c;
                $c++;
            }
            $alias = $_;
        }

        return $alias;
    }

    function get_alias_num_in_folder($id='0', $parent='0')
    {
        global $modx;

        $rs = db()->select(
            'MAX(cast(alias as SIGNED))'
            ,'[+prefix+]site_content'
            , sprintf(
                "id<>'%s' AND parent='%s' AND alias REGEXP '^[0-9]+$'"
                , (int)$id
                , (int)$parent
            )
        );
        $_ = db()->getValue($rs);
        if(empty($_)) {
            $_ = 0;
        }
        $_++;
        while(!isset($noduplex))
        {
            $rs = db()->select(
                'id'
                ,'[+prefix+]site_content'
                , sprintf(
                    "id='%s' AND parent=%s AND alias=''"
                    , $_
                    , (int)$parent
                )
            );
            if(db()->getRecordCount($rs)==0) {
                $noduplex = true;
            } else {
                $_++;
            }
        }
        return $_;
    }

    function modx_move_uploaded_file($tmp_path,$target_path) {
        global $modx;

        return $modx->move_uploaded_file($tmp_path,$target_path);

    }

    function validate_referer($flag)
    {
        global $modx;

        if(isset($_GET['frame']) && $_GET['frame']==='main')
        {
            switch($modx->manager->action)
            {
                case '3' :
                case '120' :
                case '4' :
                case '72' :
                case '27' :
                case '8' :
                case '87' :
                case '88' :
                case '11' :
                case '12' :
                case '74' :
                case '28' :
                case '35' :
                case '38' :
                case '16' :
                case '19' :
                case '22' :
                case '23' :
                case '77' :
                case '78' :
                case '18' :
                case '106' :
                case '107' :
                case '108' :
                case '100' :
                case '101' :
                case '102' :
                case '131' :
                case '200' :
                case '31' :
                case '40' :
                case '91' :
                case '41' :
                case '92' :
                case '17' :
                case '53' :
                case '13' :
                case '10' :
                case '70' :
                case '71' :
                case '59' :
                case '75' :
                case '99' :
                case '86' :
                case '76' :
                case '83' :
                case '93' :
                case '95' :
                case '9' :
                case '301' :
                case '302' :
                case '115' :
                case '112' :
                    unset($_GET['frame']);
                    $_SESSION['mainframe'] = $_GET;
                    header('Location:' . MODX_MANAGER_URL);
                    exit;
                    break;
                default :
            }
        }

        if($flag!=1) {
            return;
        }
        $referer = isset($_SERVER['HTTP_REFERER']) ? strip_tags($_SERVER['HTTP_REFERER']) : '';

        if(empty($referer)) {
            exit("A possible CSRF attempt was detected. No referer was provided by the server.");
        }

        $referer  = str_replace(array('http://', 'https://'), '//', $referer);
        $site_url = str_replace(array('http://', 'https://'), '//', MODX_SITE_URL);
        if(stripos($referer, $site_url)!==0)
            exit("A possible CSRF attempt was detected from referer: {$referer}.");
    }

    function checkToken()
    {
        global $modx;

        $clientToken = $modx->input_any('token', false);
        $serverToken = $modx->session_var('token', false);

        $_SESSION['token'] = '';

        if(!$clientToken)               return false;
        if(!$serverToken)               return false;
        if($clientToken!==$serverToken) return false;

        return true;
    }

    function makeToken()
    {
        global $modx;

        $newToken = $modx->genTokenString();
        $_SESSION['token'] = $newToken;
        return $newToken;
    }

    function remove_locks($action='all',$limit_time=120)
    {
        global $modx;

        $limit_time = time() - $limit_time;
        if($action === 'all')
        {
            $action = '';
        }
        else
        {
            $action = (int)$action;
            $action = "action={$action} and";
        }
        db()->delete('[+prefix+]active_users',"{$action} lasthit < {$limit_time}");
    }

    function getHashType($db_value='') { // md5 | v1 | phpass
        $c = substr($db_value,0,1);
        if ($c==='$') {
            return 'phpass';
        }

        if (strlen($db_value)===32) {
            return 'md5';
        }

        if($c!=='$' && strpos($db_value,'>')!==false) {
            return 'v1';
        }

        return 'unknown';
    }

    function genV1Hash($password, $seed='1')
    { // $seed is user_id basically
        global $modx;

        if(isset($modx->config['pwd_hash_algo']) && !empty($modx->config['pwd_hash_algo'])) {
            $algorithm = $modx->config['pwd_hash_algo'];
        } else {
            $algorithm = 'UNCRYPT';
        }

        $salt = md5($password . $seed);

        switch($algorithm)
        {
            case 'BLOWFISH_Y':
                $salt = '$2y$07$' . substr($salt,0,22);
                break;
            case 'BLOWFISH_A':
                $salt = '$2a$07$' . substr($salt,0,22);
                break;
            case 'SHA512':
                $salt = '$6$' . substr($salt,0,16);
                break;
            case 'SHA256':
                $salt = '$5$' . substr($salt,0,16);
                break;
            case 'MD5':
                $salt = '$1$' . substr($salt,0,8);
                break;
            case 'UNCRYPT':
                break;
        }

        if($algorithm!=='UNCRYPT')
        {
            $password = sha1($password) . crypt($password,$salt);
        } else {
            $password = sha1($salt . $password);
        }

        $result = sprintf(
            '%s>%s%s'
            , strtolower($algorithm)
            , md5($salt . $password)
            , substr(md5($salt), 0, 8)
        );

        return $result;
    }

    function getV1UserHashAlgorithm($uid)
    {
        global $modx;

        $user = db()->getObject('manager_users',"id='{$uid}'");

        if(strpos($user->password, '>')===false) {
            return 'NOSALT';
        }

        return strtoupper(
            substr(
                $user->password
                , 0
                , strpos($user->password, '>')
            )
        );
    }

    function checkHashAlgorithm($algorithm='')
    {
        if(empty($algorithm)) return '';

        switch($algorithm)
        {
            case 'BLOWFISH_Y':
                if(defined('CRYPT_BLOWFISH') && CRYPT_BLOWFISH == 1)
                {
                    if(version_compare('5.3.7', PHP_VERSION) <= 0) $result = true;
                }
                break;
            case 'BLOWFISH_A':
                if(defined('CRYPT_BLOWFISH') && CRYPT_BLOWFISH == 1) $result = true;
                break;
            case 'SHA512':
                if(defined('CRYPT_SHA512') && CRYPT_SHA512 == 1) $result = true;
                break;
            case 'SHA256':
                if(defined('CRYPT_SHA256') && CRYPT_SHA256 == 1) $result = true;
                break;
            case 'MD5':
                if(defined('CRYPT_MD5') && CRYPT_MD5 == 1 && PHP_VERSION !== '5.3.7')
                    $result = true;
                break;
            case 'UNCRYPT':
                $result = true;
                break;
        }

        if(!isset($result)) $result = false;

        return $result;
    }

    function setView($action)
    {
        $actions = explode(',', '10,100,101,102,106,107,108,11,112,113,114,115,117,74,12,120,13,131,16,17,18,19,2,200,22,23,26,27,28,29,3,300,301,31,35,38,4,40,51,53,59,70,71,72,75,76,77,78,81,83,84,86,87,88,9,91,93,95,99,998,999');
        if(in_array($action, $actions))
        {
            if(evo()->session_var('current_request_uri')) {
                $_SESSION['previous_request_uri'] = evo()->session_var('current_request_uri');
            }
            $_SESSION['current_request_uri'] = $_SERVER['REQUEST_URI'];
        }
    }

    function ab($ph)
    {
        return html_tag(
            '<li>'
            , array('class'=> $ph['label']==lang('cancel') ? 'class="mutate"' : ''),
            html_tag(
                '<a>',
                array(
                    'href'    => '#',
                    'onclick' => $ph['onclick']
                )
                , img_tag(
                    $ph['icon'],
                    array(
                        'alt' => $ph['alt']
                    )
                ) . $ph['label']
            )
        );
    }

    function newCategory($newCat)
    {
        global $modx;

        $newCatid = db()->insert(
            array('category' => db()->escape($newCat))
            , '[+prefix+]categories'
        );

        if(!$newCatid) {
            return 0;
        }
        return $newCatid;
    }

    //check if new category already exists
    function checkCategory($newCat = '')
    {
        global $modx;

        $rs = db()->select(
            'id,category'
            , '[+prefix+]categories'
            , ''
            , 'category'
        );

        if(!$rs) {
            return 0;
        }

        while($row = db()->getRow($rs)) {
            if ($row['category'] == $newCat) {
                return $row['id'];
            }
        }
        return 0;
    }

    //Get all categories
    function getCategories()
    {
        global $modx;
        $rs = db()->select(
            'id, category'
            , '[+prefix+]categories'
            , ''
            , 'category'
        );

        if(!$rs)
        {
            return array();
        }

        $resourceArray = array();
        while($row = db()->getRow($rs)) // pixelchutes
        {
            $resourceArray[] = array(
                'id'       => $row['id'],
                'category' => stripslashes($row['category'])
            );
        }
        return $resourceArray;
    }

    //Delete category & associations
    function deleteCategory($catId=0)
    {
        global $modx;

        if (!$catId) {
            return;
        }

        $resetTables = array(
            'site_plugins',
            'site_snippets',
            'site_htmlsnippets',
            'site_templates',
            'site_tmplvars',
            'site_modules'
        );

        foreach ($resetTables as $table_name) {
            db()->update(
                array('category'=>'0')
                , '[+prefix+]' . $table_name
                , sprintf("category='%d'", $catId)
            );
        }

        db()->delete(
            '[+prefix+]categories'
            , sprintf("id='%d'", $catId)
        );
    }

    /**
     *	System Alert Message Queue Display file
     *	Written By Raymond Irving, April, 2005
     *
     *	Used to display system alert messages inside the browser
     *
     */

    function sysAlert($sysAlertMsgQueque='') {
        global $modx,$_lang;

        if(!$sysAlertMsgQueque) {
            $sysAlertMsgQueque = $modx->SystemAlertMsgQueque;
            if(!$sysAlertMsgQueque) {
                return '';
            }
        }

        if(!is_array($sysAlertMsgQueque)) {
            $sysAlertMsgQueque = array($sysAlertMsgQueque);
        }

        unset($_SESSION['SystemAlertMsgQueque']);
        $_SESSION['SystemAlertMsgQueque'] = array();

        $alerts = array();
        foreach($sysAlertMsgQueque as $_) {
            $alerts[] = $_;
        }

        return $modx->parseText(
            file_get_contents(MODX_MANAGER_PATH . 'media/style/common/sysalert.tpl')
            , array(
                'alerts' => db()->escape(implode('<hr />',$alerts)),
                'title'  => $_lang['sys_alert']
            )
        );
    }

    function getMessageCount() {
        global $modx;

        if(!$modx->hasPermission('messages')) {
            return false;
        }

        $uid = $modx->getLoginUserID();

        $new = db()->getValue(
            db()->select(
                'count(id)'
                , '[+prefix+]user_messages'
                , sprintf("recipient='%s' and messageread=0", $uid)
            )
        );

        $total = db()->getValue(
            db()->select(
                'count(id)'
                , '[+prefix+]user_messages'
                , sprintf("recipient='%s'", $uid)
            )
        );

        // ajax response
        if (isset($_POST['updateMsgCount'])) {
            echo "{$new},{$total}";
            exit();
        }
        else return array('new'=>$new,'total'=>$total);
    }

    // get user's document groups
    function getMgrDocgroups($uid=0) {
        global $modx;

        if(!$uid) {
            $uid = $modx->getLoginUserID();
        }

        $rs = db()->select(
            'uga.documentgroup as documentgroup'
            , array(
                '[+prefix+]member_groups ug',
                'INNER JOIN [+prefix+]membergroup_access uga ON uga.membergroup=ug.user_group'
            )
            , sprintf("ug.member='%s'", $uid)
        );

        if(!db()->getRecordCount($rs)) {
            return array();
        }

        $documentgroup = array();
        while ($row = db()->getRow($rs)) {
            $documentgroup[] = $row['documentgroup'];
        }
        return $documentgroup;
    }

    function getMemberGroups($uid=0) {
        global $modx;

        if(!$uid) {
            $uid = $modx->getLoginUserID();
        }

        $rs = db()->select(
            'user_group,name'
            , array(
                '[+prefix+]member_groups ug',
                'INNER JOIN [+prefix+]membergroup_names ugnames ON ug.user_group=ugnames.id'
            )
            , preg_match('@^[1-9][0-9]*$@',$uid) ? sprintf("ug.member='%d'", $uid) : ''
        );

        if(!db()->getRecordCount($rs)) {
            return array();
        }

        $group = array();
        while ($row = db()->getRow($rs)) {
            $group[$row['user_group']]=$row['name'];
        }

        return $group;
    }
    /**
     *	Secure Manager Documents
     *	This script will mark manager documents as private
     *
     *	A document will be marked as private only if a manager user group
     *	is assigned to the document group that the document belongs to.
     *
     */
    function setMgrDocsAsPrivate($docid='') {
        global $modx;

        db()->update(
            array('privatemgr'=>0)
            , '[+prefix+]site_content'
            , $docid ? sprintf("id='%s'", $docid) : 'privatemgr=1'
        );

        $rs = db()->select(
            'sc.id'
            , array('[+prefix+]site_content sc',
                'LEFT JOIN [+prefix+]document_groups dg ON dg.document = sc.id',
                'LEFT JOIN [+prefix+]membergroup_access mga ON mga.documentgroup = dg.document_group'
            )
            , $docid > 0 ? sprintf("sc.id='%s' AND mga.id > 0", $docid) : 'mga.id > 0'
        );

        $ids = db()->getColumn('id',$rs);

        if(!$ids) {
            return '';
        }

        $ids = implode(',', $ids);
        db()->update(
            array('privatemgr'=>1)
            , '[+prefix+]site_content'
            , sprintf('id IN (%s)', $ids)
        );
        return $ids;
    }

    /**
     *	Secure Web Documents
     *	This script will mark web documents as private
     *
     *	A document will be marked as private only if a web user group
     *	is assigned to the document group that the document belongs to.
     *
     */
    function setWebDocsAsPrivate($docid='') {
        global $modx;

        db()->update(
            array('privateweb'=>0)
            , '[+prefix+]site_content'
            , $docid ? sprintf("id='%s'", $docid) : 'privateweb=1'
        );

        $rs = db()->select(
            'DISTINCT sc.id'
            , array(
                '[+prefix+]site_content sc',
                'LEFT JOIN [+prefix+]document_groups dg ON dg.document = sc.id',
                'LEFT JOIN [+prefix+]webgroup_access wga ON wga.documentgroup = dg.document_group'
            )
            , $docid ? sprintf("sc.id='%s' AND wga.id > 0", $docid) : 'wga.id > 0'
        );

        $ids = db()->getColumn('id',$rs);

        if($ids) {
            return '';
        }

        $ids = implode(',', $ids);
        db()->update(
            array('privateweb'=>1)
            , '[+prefix+]site_content'
            , sprintf("id IN (%s)", $ids)
        );
        return $ids;
    }

    function getStylePath() {
        return MODX_MANAGER_PATH . 'media/style/';
    }

    function renderTabPane($ph) {
        global $modx;

        $style_path = $this->getStylePath() . 'common/block_tabpane.tpl';

        if(!is_file($style_path)) {
            return '';
        }

        if(!isset($ph['id'])) {
            $ph['id'] = 'tab' . substr(uniqid('id', true), 0, 13);
        }
        if(!isset($ph['tab-pages'])) {
            $ph['tab-pages'] = 'content';
        } elseif(is_array($ph['tab-pages'])) {
            $ph['tab-pages'] = join("\n", $ph['tab-pages']);
        }

        return $modx->parseText(
            file_get_contents($style_path)
            , $ph
        );
    }

    function renderTabPage($ph) {
        global $modx;

        $style_path = $this->getStylePath() . 'common/block_tabpage.tpl';

        if(!is_file($style_path)) {
            return '';
        }

        if(!isset($ph['id'])) {
            $ph['id'] = 'id' . substr(uniqid('id', true),0,13);
        }
        if(!isset($ph['title'])) {
            $ph['title'] = 'title';
        }

        if(!isset($ph['content'])) {
            $ph['content'] = 'content';
        }

        return $modx->parseText(
            file_get_contents($style_path)
            , $ph
        );
    }

    function renderSection($ph) {
        global $modx;

        $style_path = $this->getStylePath() . 'common/block_section.tpl';

        if(!is_file($style_path)) {
            return '';
        }

        if(!isset($ph['id'])) {
            $ph['id'] = 'id' . substr(uniqid('id', true),0,13);
        }
        if(!isset($ph['title'])) {
            $ph['title'] = 'title';
        }
        if(!isset($ph['content'])) {
            $ph['content'] = 'content';
        }

        return $modx->parseText(
            file_get_contents($style_path)
            , $ph
        );
    }

    function renderTr($ph) {
        global $modx;

        $style_path = $this->getStylePath() . 'common/block_tr.tpl';

        if(!is_file($style_path)) {
            return '';
        }


        if(!isset($ph['id'])) {
            $ph['id'] = 'id' . substr(uniqid('id', true),0,13);
        }
        if(!isset($ph['title'])) {
            $ph['title'] = 'title';
        }
        if(!isset($ph['content'])) {
            $ph['content'] = 'content';
        }

        return $modx->parseText(
            file_get_contents($style_path)
            , $ph);
    }

    function isAllowed($id)
    {
        global $modx;

        if(!$id)
        {
            if(!$modx->input_any('pid')) {
                return true;
            }

            $id = $modx->input_any('pid');
        }

        if(!$modx->conf_var('allowed_parents')) {
            return true;
        }

        if(!isset($modx->user_allowed_docs)) {
            $modx->user_allowed_docs = $this->getUserAllowedDocs();
        }

        if(!in_array($id, (array)$modx->user_allowed_docs)) {
            return false;
        }

        return true;
    }

    function isContainAllowed($id)
    {
        global $modx;
        if($this->isAllowed($id)) {
            return true;
        }

        $childlen = $modx->getChildIds($id);
        if(empty($childlen)) {
            return false;
        }

        foreach($childlen as $child)
        {
            if(in_array($child,$modx->user_allowed_docs))
            {
                return true;
            }
        }
        return false;
    }

    function getUserAllowedDocs()
    {
        global $modx;

        $modx->user_allowed_docs = array();
        $allowed_parents = explode(
            ','
            , str_replace(
                array(' ','|')
                , ','
                , preg_replace(
                    '@\s+@'
                    , ' '
                    , trim($modx->config['allowed_parents'])
                )
            )
        );

        if(!$allowed_parents) {
            return '';
        }

        $_ = array();
        foreach($allowed_parents as $parent)
        {
            $parent = trim($parent);
            $children = $modx->getChildIds($parent);
            $_[] = $parent;
            foreach($children as $child) {
                $_[] = $child;
            }
        }
        $modx->user_allowed_docs = $_;
        return $modx->user_allowed_docs;
    }

    function getUploadMaxsize()
    {
        $upload_max_filesize = ini_get('upload_max_filesize');
        $post_max_size       = ini_get('post_max_size');
        $memory_limit        = ini_get('memory_limit');
        if(version_compare($upload_max_filesize, $post_max_size,'<')) {
            $limit_size = $upload_max_filesize;
        } else {
            $limit_size = $post_max_size;
        }

        if(version_compare($memory_limit, $limit_size,'<')) {
            $limit_size = $memory_limit;
        }
        return $limit_size;
    }

    function getTplModule()
    {
        ob_start();
        include_once(MODX_MANAGER_PATH . 'actions/header.inc.php');
        echo '[+content+]';
        include_once(MODX_MANAGER_PATH . 'actions/footer.inc.php');
        return ob_get_clean();
    }

    function loadDatePicker($path) {
        global $modx;
        include_once($path);
        $dp = new DATEPICKER();
        return $modx->mergeSettingsContent($dp->getDP());
    }
}
