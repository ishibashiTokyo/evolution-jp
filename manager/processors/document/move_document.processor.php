<?php
if(!isset($modx) || !$modx->isLoggedin()) exit;
if(!$modx->hasPermission('move_document'))   {$e->setError(3);$e->dumpError();}
if(!$modx->hasPermission('edit_document'))   {$e->setError(3);$e->dumpError();}

if($_REQUEST['id']==$_REQUEST['new_parent']) {$e->setError(600); $e->dumpError();}
if($_REQUEST['id']=='')                      {$e->setError(601); $e->dumpError();}
if($_REQUEST['new_parent']=='')              {echo '<script type="text/javascript">parent.tree.ca = "open";</script>';$e->setError(602); $e->dumpError();}

$tbl_site_content = $modx->getFullTableName('site_content');
$doc_id = $_REQUEST['id'];
if(strpos($doc_id,','))
{
	$doc_ids = explode(',',$doc_id);
	$doc_id = substr($doc_id,0,strpos($doc_id,','));
}
else $doc_ids[] = $doc_id;

$rs = $modx->db->select('parent',$tbl_site_content,"id='{$doc_id}'");
if(!$rs) {
	exit("An error occured while attempting to find the resource's current parent.");
}
$current_parent = $modx->db->getValue($rs);
$new_parent = (int)$_REQUEST['new_parent'];

// check user has permission to move resource to chosen location
if ($modx->config['use_udperms'] == 1 && $current_parent != $new_parent)
{
	if (!$modx->checkPermissions($new_parent))
	{
		include_once(MODX_MANAGER_PATH . 'actions/header.inc.php');
		?>
		<script type="text/javascript">parent.tree.ca = '';</script>
		<div class="sectionHeader"><?php echo $_lang['access_permissions']; ?></div>
		<div class="sectionBody">
		<p><?php echo $_lang['access_permission_parent_denied']; ?></p>
		</div>
		<?php
		include_once(MODX_MANAGER_PATH . 'actions/footer.inc.php');
		exit;
	}
}
$children= allChildren($doc_id);
$alert = '';
if($current_parent == $new_parent)
{
	$alert = $_lang["move_resource_new_parent"];
}
elseif (in_array($new_parent, $children))
{
	$alert = $_lang["move_resource_cant_myself"];
}
else
{
	$rs = $modx->db->update('isfolder=1',$tbl_site_content,"id='{$new_parent}'");
	if(!$rs)
		$alert = "An error occured while attempting to change the new parent to a folder.";

	// increase menu index
	if ($modx->config['auto_menuindex'] === null || $modx->config['auto_menuindex'])
	{
		$menuindex = $modx->db->getValue($modx->db->select('max(menuindex)',$tbl_site_content,"parent='{$new_parent}'"))+1;
	}
	else $menuindex = 0;

	$user_id = $modx->getLoginUserID();
	if(is_array($doc_ids))
	{
		foreach($doc_ids as $v)
		{
			update_parentid($v,$new_parent,$user_id,$menuindex);
			$menuindex++;
		}
	}

	// finished moving the resource, now check to see if the old_parent should no longer be a folder.
	$rs = $modx->db->select('count(*) as count',$tbl_site_content,"parent='{$current_parent}'");
	if(!$rs)
		$alert = "An error occured while attempting to find the old parents' children.";
	
	$row = $modx->db->getRow($rs);

	if(!$row['count'])
	{
		$rs = $modx->db->update('isfolder=0','[+prefix+]site_content',"id='{$current_parent}'");
		if(!$rs)
			$alert = 'An error occured while attempting to change the old parent to a regular resource.';
	}
}

if($alert)
{
	$modx->webAlertAndQuit(
		$alert
		, "javascript:parent.tree.ca='open';window.location.href='index.php?a=51&id={$doc_id}';"
		);
	exit;
}

$modx->clearCache();

if($new_parent!==0) {
	header("Location: index.php?a=120&id={$current_parent}&r=1");
	exit;
}

header("Location: index.php?a=2&r=1");

exit;


function allChildren($docid)
{
	global $modx;
	$tbl_site_content = $modx->getFullTableName('site_content');
	$children= array();
	$rs = $modx->db->select('id',$tbl_site_content,"parent='{$docid}'");
	if(!$rs)
	{
		exit("An error occured while attempting to find all of the resource's children.");
	}

    if ($numChildren= $modx->db->getRecordCount($rs))
    {
        while ($child= $modx->db->getRow($rs))
        {
            $children[]= $child['id'];
            $nextgen= allChildren($child['id']);
            foreach($nextgen as $k=>$v) {
                $children[$k] = $v;
            }
        }
    }
    return $children;
}

function update_parentid($doc_id,$new_parent,$user_id,$menuindex)
{
	global $modx, $_lang;
	$tbl_site_content = $modx->getFullTableName('site_content');
	if (!$modx->config['allow_duplicate_alias'])
	{
		$rs = $modx->db->select("IF(alias='', id, alias) AS alias",$tbl_site_content, "id='{$doc_id}'");
		$alias = $modx->db->getValue($rs);
		$rs = $modx->db->select('id',$tbl_site_content, "parent='{$new_parent}' AND (alias='{$alias}' OR (alias='' AND id='{$alias}'))");
		$find = $modx->db->getRecordcount($rs);
		if(0<$find)
		{
			$target_id = $modx->db->getValue($rs);
			$url = "javascript:parent.tree.ca='open';window.location.href='index.php?a=27&id={$doc_id}';";
			$modx->webAlertAndQuit(sprintf($_lang["duplicate_alias_found"], $target_id, $alias), $url);
			exit;
		}
	}
	$field['parent']    = $new_parent;
	$field['editedby']  = $user_id;
	$field['menuindex'] = $menuindex;
	$rs = $modx->db->update($field,$tbl_site_content,"id='{$doc_id}'");
	if(!$rs)
	{
		exit("An error occured while attempting to move the resource to the new parent.");
	}
}
