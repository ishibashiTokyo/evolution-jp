<?php
/**
 * QuickManager+
 *
 * @author      Mikko Lammi, www.maagit.fi
 * @license     GNU General Public License (GPL), http://www.gnu.org/copyleft/gpl.html
 * @version     1.5.6 updated 12/01/2011
 */

if(class_exists('Qm')) {
    return;
}

class Qm {
    public $params;
    //_______________________________________________________
    function __construct($dummy1='',$dummy2='') {
		global $modx;

        if($modx->input_get('a')==83) return;

        if(!$modx->event->params) {
            $modx->documentOutput = 'QuickManagerをインストールし直してください。';
            return;
        }

        $this->params = $modx->event->params;

        if ($this->getParam('disabled')) {
            if (in_array(
                $modx->documentIdentifier
                , explode(',', $this->getParam('disabled') )
            )) {
                return;
            }
        }

        // Get plugin parameters
        $this->jqpath         = 'manager/media/script/jquery/jquery.min.js';
        $this->loadfrontendjq = $this->getParam('loadfrontendjq');
        $this->loadtb         = $this->getParam('loadtb');
        $this->tbwidth        = $this->getParam('tbwidth');
        $this->tbheight       = $this->getParam('tbheight');
        $this->hidefields     = $this->getParam('hidefields');
        $this->hidetabs       = $this->getParam('hidetabs','');
        $this->hidesections   = $this->getParam('hidesections','');
        $this->addbutton      = $this->getParam('addbutton');
        $this->tpltype        = $this->getParam('tpltype');
        $this->tplid          = $this->getParam('tplid','');
        $this->custombutton   = $this->getParam('custombutton','');
        $this->managerbutton  = $this->getParam('managerbutton');
        $this->logout         = $this->getParam('logout');
        $this->autohide       = $this->getParam('autohide');
        $this->editbuttons    = $this->getParam('editbuttons');
        $this->editbclass     = $this->getParam('editbclass');
        $this->newbuttons     = $this->getParam('newbuttons');
        $this->newbclass      = $this->getParam('newbclass');
        $this->tvbuttons      = $this->getParam('tvbuttons');
        $this->tvbclass       = $this->getParam('tvbclass');

        if(version_compare($this->getParam('version', 0),'1.5.6','<')) {
            $modx->documentOutput = 'QuickManagerをアップデートしてください。';
            return;
        }

        // Includes
        include_once MODX_BASE_PATH.'assets/plugins/qm/mcc.class.php';

        // Run plugin
        $this->Run();
    }

    function getParam($key, $default=null) {
        if (!isset($this->params[$key])) {
            return $default;
        }
        if(strtolower($this->params[$key])==='true') {
            $this->params[$key] = true;
        }
        elseif(strtolower($this->params[$key])==='false') {
            $this->params[$key] = false;
        }
        return $this->params[$key];
    }

    //_______________________________________________________
    function run() {
        // Include MODx manager language file
        global $modx, $_lang;
        // Run plugin based on event
        switch ($modx->event->name) {
            // Save document
            case 'OnDocFormSave':
                include_once __DIR__ . '/inc/on_doc_form_save.inc';
                break;
            // Display page in front-end
            case 'OnWebPagePrerender':
                include_once __DIR__ . '/inc/on_web_page_prerender.inc';
                break;
            // Edit document in ThickBox frame (MODx manager frame)
            case 'OnDocFormPrerender':
                include_once __DIR__ . '/inc/on_doc_form_prerender.inc';
                break;
            case 'OnManagerLogout': // Where to logout
                // Only if cancel editing the document and QuickManager is in use
                if ($_REQUEST['quickmanager'] !== 'logout' || $this->logout === 'manager') {
                    break;
                }
                // Redirect to document id
                $modx->sendRedirect(
                    $modx->makeUrl($modx->input_any('logoutid', 0))
                    , 0
                    , 'REDIRECT_HEADER'
                    , 'HTTP/1.1 301 Moved Permanently'
                );
                break;
        }
    }

    // Replace [*#tv*] with QM+ edit TV button placeholders
    function replaceTvButtons() {
        global $modx;
        if (!$this->getParam('tvbuttons') || $modx->event->name !== 'OnParseDocument') {
            return;
        }

        if(strpos($modx->documentOutput,'[*#')===false) {
            return;
        }

        $m = $modx->getTagsFromContent($modx->documentOutput, '[*#', '*]');
        if(!$m) {
            return;
        }
        $replace = array();
        foreach($m[1] as $i=>$tv_name) {
            if(strpos($tv_name,':')!==false) {
                $tv_name = substr($tv_name, 0, strpos($tv_name, ':'));
            }
            $replace[$i] = sprintf(
                '<!-- %s %s -->%s'
                , $this->getParam('tvbclass')
                , $tv_name
                , $m[0][$i]
            );
        }
        $modx->documentOutput = str_replace($m[0], $replace, $modx->documentOutput);
    }
    // Check if user has manager access permissions to current document
    //_______________________________________________________
    function checkAccess() {
        global $modx;

        if(!isset($modx->documentIdentifier) || !$modx->documentIdentifier) {
            return false;
        }

        // If user is admin (role = 1)
        if ($_SESSION['mgrRole'] == 1) {
            return true;
        }

        // Check if current document is assigned to one or more doc groups
        $result= $modx->db->select(
            'id'
            ,'[+prefix+]document_groups'
            , sprintf("document='%s'", $modx->documentIdentifier)
        );

        // If document is assigned to one or more doc groups, check access
        if (!$modx->db->getRecordCount($result)) {
            return true;
        }

// Get document groups for current user
        if ($_SESSION['mgrDocgroups']) {
            // Check if user has access to current document
            $result = $modx->db->select(
                'id'
                , '[+prefix+]document_groups'
                , sprintf(
                    'document=%d AND document_group IN (%s)'
                    , (int) $modx->documentIdentifier
                    , implode(',', $_SESSION['mgrDocgroups'])
                )
            );
            if ($modx->db->getRecordCount($result)) {
                return true;
            }
        }

        return false;
    }

    // Function from: manager/processors/cache_sync.class.processor.php
    //_____________________________________________________
    function getParents($id, $path = '') {
        // modx:returns child's parent
        global $modx;
        if(empty($this->aliases)) {
            $qh = $modx->db->select(
                "id, IF(alias='', id, alias) AS alias, parent"
                , $modx->getFullTableName('site_content')
            );
            if ($qh && $modx->db->getRecordCount($qh) > 0) {
                while ($row = $modx->db->getRow($qh)) {
                    $this->aliases[$row['id']] = $row['alias'];
                    $this->parents[$row['id']] = $row['parent'];
                }
            }
        }
        if (isset($this->aliases[$id])) {
            $path = $this->aliases[$id] . ($path != '' ? '/' : '') . $path;
            return $this->getParents($this->parents[$id], $path);
        }
        return $path;
    }

    // Create TV buttons if user has permissions to TV
    //_____________________________________________________
    function createTvButtons($matches) {
        global $modx;
        // Get TV caption for button title
        $tv = $modx->getTemplateVar($matches[1]);

        // If caption is empty this must be a "build-in-tv-field" like pagetitle etc.
        if ($tv['caption'] == '') {
            $access = true;
            $caption = $this->getDefaultTvCaption($matches[1]);
        } else {
            $access = $this->checkTvAccess($tv['id']);
            $caption = $tv['caption'];
        }

        // Return TV button link if access
        if ($access && $caption != '') {
            return sprintf(
                '<span class="%s"><a class="colorbox" href="%sindex.php?id=%s&amp;quickmanagertv=1&amp;tvname=%s"><span>%s</span></a></span>'
                , $this->tvbclass
                , $modx->config['site_url']
                , $modx->documentIdentifier
                , urlencode($matches[1])
                , $caption
            );
        }
        return '';
    }

    // Check user access to TV
    //_____________________________________________________
    function checkTvAccess($tvId) {
        global $modx;

        $access = false;

        // If user is admin (role = 1)
        if ($_SESSION['mgrRole'] == 1 && !$access) {
            $access = true;
        }

        // Check permission to TV, is TV in document group?
        if (!$access) {
            $result = $modx->db->select(
                'id'
                , $modx->getFullTableName('site_tmplvar_access')
                , sprintf('tmplvarid=%s', $tvId)
            );
            $rowCount = $modx->db->getRecordCount($result);
            // TV is not in any document group
            if ($rowCount == 0) {
                $access = true;
            }
        }
        // Check permission to TV, TV is in document group
        if (!$access && $this->docGroup != '') {
            $result = $modx->db->select(
                'id'
                , $modx->getFullTableName('site_tmplvar_access')
                , sprintf(
                    'tmplvarid=%s AND documentgroup IN (%s)'
                    , $tvId
                    , $this->docGroup
                )
            );
            $rowCount = $modx->db->getRecordCount($result);
            if ($rowCount >= 1) { $access = true; }
        }
        return $access;
    }

    // Get default TV ("build-in" TVs) captions
    //_____________________________________________________
    function getDefaultTvCaption($name) {
        global $_lang;

        $caption = array(
            'pagetitle'   => $_lang['resource_title'],
            'longtitle'   => $_lang['long_title'],
            'description' => $_lang['resource_description'],
            'content'     => $_lang['resource_content'],
            'menutitle'   => $_lang['resource_opt_menu_title'],
            'introtext'   => $_lang['resource_summary']
        );

        if(isset($caption[$name])) {
            return $caption[$name];
        }

        return '';
    }

    // Check that a document isn't locked for editing
    //_____________________________________________________
    function checkLocked() {
        global $modx;

        $result = $modx->db->select(
            'internalKey'
            , $modx->getFullTableName('active_users')
            , sprintf(
                '`action`=27 AND `internalKey`!=%d AND `id`=%d'
                , (int) $_SESSION['mgrInternalKey']
                , (int) $modx->documentIdentifier
            )
        );

        return !($modx->db->getRecordCount($result) === 0);
    }

    // Set document locked on/off
    //_____________________________________________________
    function setLocked($locked) {
        global $modx;

        // Set document locked
        if ($locked) {
            $fields['id']     = $modx->documentIdentifier;
            $fields['action'] = 27;
        } else {
            // Set document unlocked
            $fields['id'] = 'NULL';
            $fields['action'] = 2;
        }
        $modx->db->update(
            $fields
            , $modx->getFullTableName('active_users')
            , sprintf('internalKey=%d', (int)$_SESSION['mgrInternalKey'])
        );
    }

    // Save TV
    //_____________________________________________________
    function saveTv($tvName)
    {
        global $modx;

        $result = null;
        if (preg_match('@^[1-9][0-9]*$@', $modx->input_post('tvid'))) {
            $tvId = $modx->input_post('tvid');
        } else {
            $tvId = 0;
        }
        if($tvId) {
            $tvContent = $modx->input_post('tv' . $tvId, '');
        } else {
            $tvContent = $modx->input_post('tv' . $tvName, '');
        }

        // Escape TV content
        $tvName = $modx->db->escape($tvName);
        $tvContent = $modx->db->escape($tvContent);

        // Invoke OnBeforeDocFormSave event
        $tmp = array('mode'=>'upd', 'id'=>$modx->documentIdentifier);
        $modx->invokeEvent('OnBeforeDocFormSave', $tmp);

        // Handle checkboxes and other arrays, TV to be saved must be e.g. value1||value2||value3
        $tvContentTemp = '';
        if (is_array($tvContent)) {
            foreach($tvContent as $key => $value) {
                $tvContentTemp .= $value . '||';
            }
            $tvContentTemp = substr($tvContentTemp, 0, -2);  // Remove last ||
            $tvContent = $tvContentTemp;
        }

        // Save TV
        if ($tvId) {
            $result = $modx->db->select(
                'id'
                , $modx->getFullTableName('site_tmplvar_contentvalues')
                , sprintf(
                    "`tmplvarid`=%d AND `contentid`='%s'"
                    , (int) $tvId
                    , $modx->documentIdentifier
                )
            );

            // TV exists, update TV
            if($modx->db->getRecordCount($result)) {
                $sql = sprintf(
                    "UPDATE %s SET `value`='%s' WHERE `tmplvarid`=%d AND `contentid`=%d"
                    , $modx->getFullTableName('site_tmplvar_contentvalues')
                    , $tvContent
                    , $tvId
                    , (int) $modx->documentIdentifier
                );
            } else {
                // TV does not exist, create new TV
                $sql = sprintf(
                    "INSERT INTO %s (tmplvarid, contentid, value) VALUES(%d, %d, '%s')"
                    , $modx->getFullTableName('site_tmplvar_contentvalues')
                    , (int) $tvId
                    , (int) $modx->documentIdentifier
                    , $tvContent
                );
            }

            // Page edited by
            $modx->db->update(
                array(
                    'editedon'=>$_SERVER['REQUEST_TIME'],
                    'editedby'=>$_SESSION['mgrInternalKey']
                )
                , $modx->getFullTableName('site_content')
                , sprintf('id=%d', (int)$modx->documentIdentifier)
            );
        } else {
            // Save default field, e.g. pagetitle
            $sql = sprintf(
                "UPDATE %s SET `%s`='%s',`editedon`='%s',`editedby`=%d WHERE `id`=%d"
                , $modx->getFullTableName('site_content')
                , $tvName
                , $tvContent
                , $_SERVER['REQUEST_TIME']
                , (int)$_SESSION['mgrInternalKey']
                , (int)$modx->documentIdentifier
            );
        }
        // Update TV
        if($sql) {
            $result = $modx->db->query($sql);
        }
        // Log possible errors
        if(!$result) {
            $modx->logEvent(0, 0, "<p>Save failed!</p><strong>SQL:</strong><pre>{$sql}</pre>", 'QuickManager+');
        } else {
            // No errors
            // Invoke OnDocFormSave event
            $tmp = array('mode'=>'upd', 'id'=>$modx->documentIdentifier);
            $modx->invokeEvent('OnDocFormSave', $tmp);
            // Clear cache
            $modx->clearCache();
        }
    }

    function get_img_prev_src() {
        $src = <<< EOT
<div id="qm-tv-image-preview"><img class="qm-tv-image-preview-drskip qm-tv-image-preview-skip" src="[+site_url+][tv_value+]" alt="" /></div>
<script type="text/javascript" charset="UTF-8">
jQuery(function()
{
	var previewImage = "#tv[+tv_name+]";
	var siteUrl = "[+site_url+]";
	
	OriginalSetUrl = SetUrl; // Copy the existing Image browser SetUrl function
	SetUrl = function(url, width, height, alt)
	{	// Redefine it to also tell the preview to update
		OriginalSetUrl(url, width, height, alt);
		jQuery(previewImage).trigger("change");
	}
	jQuery(previewImage).change(function()
	{
		jQuery("#qm-tv-image-preview").empty();
		if (jQuery(previewImage).val()!="" )
		{
			jQuery("#qm-tv-image-preview").append('<img class="qm-tv-image-preview-drskip qm-tv-image-preview-skip" src="' + siteUrl + jQuery(previewImage).val()  + '" alt="" />');
		}
		else
		{
			jQuery("#qm-tv-image-preview").append("");
		}
	});
});
</script>
EOT;
        return $src;
    }

    function getView($tpl, $ph=array()) {
        global $modx, $_lang;

        $src = file_get_contents(__DIR__ . '/view/' . $tpl);
        $src = $modx->rewriteUrls(
            $modx->mergeChunkContent(
                $modx->mergeSettingsContent(
                    $modx->mergeDocumentContent($src)
                )
            )
        );
        return $modx->parseText(
            $modx->parseText($src, $_lang, '[%', '%]')
        , $ph);
    }
}
