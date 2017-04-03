<?php
/*
 * API for Resource(Document)
 *
 * リソース(ドキュメント)に対する編集機能を提供します。
 *
 */

class Document extends ElementBase
{
	//リソースのステータス一覧
	const ST_RELEASED = 'released';
	const ST_DRAFT		= 'draft';

	private $status	 = 'released'; // リソースの状態(本番:released、下書き:draft等)
	private $tv	 = array(); // Template Value

	//日付処理が必要なカラム
	private $content_type_date = array('pub_date','unpub_date','createdon','editedon','deletedon');
		
	/*
	 * __construct
	 *
	 * @param $id		リソースID(blank=New resource)
	 * @param $level ログレベル
	 * @return none
	 *
	 */
	public function __construct($id='',$level=''){
		parent::__construct('Document Object API','resource',$id,$level);

		if(empty($id) ){
			$this->tv = array();
		}else{
			$this->load($id);
		}
	}

	/*
	 * ドキュメントのすべてのフィールドに対応した既定の値のセットを取得します。
	 */
	protected function getDefaultContent(){
		return array(
			'id' => null ,
			'type' => null ,
			'contentType' => null ,
			'pagetitle' => '' ,
			'longtitle' => '' ,
			'description' => '' ,
			'alias' => '' ,
			'link_attributes' => '' ,
			'published' => null ,
			'pub_date' => null ,
			'unpub_date' => null ,
			'parent' => null ,
			'isfolder' => null ,
			'introtext' => null ,
			'content' => null ,
			'richtext' => null ,
			'template' => null ,
			'menuindex' => 'auto' ,
			'searchable' => null ,
			'cacheable' => null ,
			'createdby' => null ,
			'createdon' => 'now()' ,
			'editedby' => null ,
			'editedon' => 'now()' ,
			'deleted' => null ,
			'deletedon' => null ,
			'deletedby' => null ,
			'publishedon' => null ,
			'publishedby' => null ,
			'menutitle' => '' ,
			'donthit' => null ,
			'haskeywords' => null ,
			'hasmetatags' => null ,
			'privateweb' => null ,
			'privatemgr' => null ,
			'content_dispo' => null ,
			'hidemenu' => null
			);
	}

	/*
	 * リソース値の取得
	 *
	 * fieldの先頭に「tv.」をつけるとTVを取得する
	 *
	 * @param $field Resource column name
	 * @return string
	 *
	 */
	public function get($field='content'){
		$value = parent::get($field);
		if($value !== false){
			return $value;
		}
		$field = $this->getTVName($field);
		if( $field !== false ){			
			return $this->getTV($field);
		}
		return false;
	}

	/*
	 * TV取得(名前ベース)
	 *
	 * 同じ名前のTVは通常存在しない
	 * get()と違い先頭に「tv.」があってはいけない
	 *
	 * @param $name TV名
	 * @return string/false
	 *
	 */
	public function getTV($name){
		foreach( $this->tv as $k => $v ){
			if( $v['name'] == $name ){
				return $v['value'];
			}
		}
		return false;
	}

	/*
	 * TV取得(idベース)
	 *
	 * @param $id TVID
	 * @return string/false
	 *
	 */
	public function getTVbyID($id){
		if( !empty($id) && array_key_exists($id,$this->tv) ){
			return $this->tv[$id]['value'];
		}
		return false;						
	}

	/*
	 * すべてのTV取得
	 *
	 * フォーマットは次のとおり。
	 *		[TVID]			 ... TVID
	 *			 [name]		... TV名
	 *			 [value]	 ... TV値
	 *			 [default] ... TVデフォルト値
	 *
	 * @param none
	 * @return string/false
	 *
	 */
	public function getAllTVs(){
		return $this->tv;
	}

	/*
	 * TVID取得
	 *
	 * 先頭に「tv.」はつけてはいけない
	 * 
	 * @param $name テンプレート変数名
	 * @return int/false
	 *
	 */
	public function getTVID($name){
		foreach( $this->tv as $k => $v ){
			if( $v['name'] == $name ){
				return $k;
			}
		}
		return false;
	}

	/*
	 * リソースの値を設定します。
	 *
	 * @param $field 値を更新するフィールドの名前。先頭にプレフィックス「tv.」をつけると値はTVに設定されます。
	 * @param $val 指定されたフィールドの新しい値。
	 * @return bool
	 *
	 */
	public function set($field='content',$val=''){
		$tv = $this->getTVName($field);
		if( $tv !== false ){
			return $this->setTV($tv,$val);
		}
		else if ($field == 'template') {
			// setTemplatebyIDメソッドはcontentも更新する
			return parent::get('template') == $val
				|| $this->setTemplatebyID($val);
		}
		return parent::set($field,$val);
	}

	/*
	 * TV設定
	 *
	 * @param $name TV名
	 * @param $val	値(無指定、もしくはnull指定でデフォルト値に戻す)
	 * @return bool
	 *
	 */
	public function setTV($name,$val=null){
		foreach( $this->tv as $k => $v ){			
			if( $v['name'] == $name ){
				return $this->setTVbyID($k,$val);
			}
		}
		$this->logWarn('TV not exist:'.$name);
		return false;
	}

	/*
	 * TV設定(idベース)
	 *
	 * @param $id	 TVID
	 * @param $val	値(無指定、もしくはnull指定でデフォルト値に戻す)
	 * @return bool
	 *
	 */
	public function setTVbyID($id,$val=null){
		if( !empty($id) && array_key_exists($id,$this->tv) ){
			if( is_null($val) ){
				$this->tv[$id]['value'] = $this->tv[$id]['default'];
			}else{
				$this->tv[$id]['value'] = $val;
			}
			return true;
		}
		$this->logWarn('TV not exist:'.$name);
		return false;
	}

	/*
	 * すべてのTVを一括設定
	 *
	 * フォーマットは次の通り
	 *		[TVID]			 ... TVID
	 *			 [value]	 ... TV値
	 *
	 * ※getAllTVs()のフォーマットに合わせてます
	 * ※配列にnameやdefaultが含まれていても無視します
	 * ※valueにnullを設定するとデフォルト値を採用
	 *
	 * @param $tv TV設定
	 * @return bool
	 *
	 */
	public function setAllTVs($tv){
		if( !is_array($tv) ){ return false; }
		foreach( $this->tv as $k => $v){
			if( isset($tv[$k]) ){
				$this->setTVbyID($k,$tv[$k]['value']);
			}
		}
		return true;
	}

	/*
	 * テンプレートの指定
	 *
	 * テンプレートを名前で指定する。
	 *
	 * ※tidに合わせて$this->tvを変更
	 * ※無効なテンプレートIDの場合、tidは0になる
	 *
	 * @param $name テンプレート名
	 * @return bool
	 *
	 */
	public function setTemplate($name){
		$rs	= parent::$modx->db->select('id','[+prefix+]site_templates',"templatename= '" . parent::$modx->db->escape($name) . "'");
		if( $row = parent::$modx->db->getRow($rs) ){
			return $this->setTemplatebyID($row['id']);
		}
		$this->logWarn('無効なテンプレート名を指定しています。');
		
		return false;
	}

	/*
	 * テンプレートの指定(idベース)
	 *
	 * ※tidに合わせて$this->tvを変更
	 * ※無効なテンプレートIDの場合、tidは0になる
	 *
	 * @param $tid テンプレートID
	 * @return bool
	 *
	 */
	public function setTemplatebyID($tid){
		if( !parent::isInt($tid,0) ){
			return false;
		}
		if( $tid != 0 ){
			$rs = parent::$modx->db->select('id','[+prefix+]site_templates',"id= $tid");
			if( !($row = parent::$modx->db->getRow($rs)) ){
				$this->logWarn('無効なテンプレートIDを指定しています。');
				$tid = 0;
			}
		}
		parent::set('template', $tid);
		//tv読み直し
		$this->tv = array();
		$docid = $this->getElementId();
		if( self::documentExist($docid) ){
			//tv読み込み(値付)
			$sql = <<< SQL_QUERY
SELECT tv.id
			,tv.name
			,IFNULL(tvc.value,tv.default_text) AS value
			,tv.default_text
FROM [+prefix+]site_tmplvars AS tv
	LEFT JOIN [+prefix+]site_tmplvar_templates AS tvt
		ON tvt.tmplvarid = tv.id
	LEFT JOIN [+prefix+]site_tmplvar_contentvalues AS tvc
		ON tvc.tmplvarid = tv.id AND tvc.contentid = {$docid}
WHERE tvt.templateid = {$tid}

SQL_QUERY;

			$sql = str_replace('[+prefix+]',parent::$modx->db->config['table_prefix'],$sql);
			$rs	= parent::$modx->db->query($sql);
			while( $row = parent::$modx->db->getRow($rs) ){
				$this->tv[$row['id']]['name']		= $row['name'];
				$this->tv[$row['id']]['value']	 = $row['value'];
				$this->tv[$row['id']]['default'] = $row['default_text'];
			}
		}else{
			//tv読み込み(値無)
			$sql = <<< SQL_QUERY
SELECT tv.id
		,tv.name
		,tv.default_text
FROM [+prefix+]site_tmplvars AS tv
	LEFT JOIN [+prefix+]site_tmplvar_templates AS tvt 
		ON tvt.tmplvarid = tv.id
	LEFT JOIN [+prefix+]site_templates AS st
	ON st.id = tvt.templateid
WHERE st.id = {$tid}
SQL_QUERY;

			$sql = str_replace('[+prefix+]',parent::$modx->db->config['table_prefix'],$sql);
			$rs	= parent::$modx->db->query($sql);
			while( $row = parent::$modx->db->getRow($rs) ){
				$this->tv[$row['id']]['name']		= $row['name'];
				$this->tv[$row['id']]['value']	 = $row['default_text'];
				$this->tv[$row['id']]['default'] = $row['default_text'];
			}
		}	
		return true;
	}

	/*
	 * リソースの読み込み
	 *
	 *	※draftの読み込み機能は廃止予定
	 *
	 * @param $id リソースID
	 * @param $status 読み込むリソースのステータス
	 * @return bool	
	 *
	 */
	public function load($id,$status=self::ST_RELEASED){
		//初期化
		$this->setContent();
		$this->tv = array();

		if( !parent::isInt($id,1) ){
			$this->logerr('リソースIDの指定が不正です。');
			return false;
		}else{
			$rs = parent::$modx->db->select('*','[+prefix+]site_content','id='.$id);
			$row = parent::$modx->db->getRow($rs);
			if( empty($row) ){
				$this->logerr('リソースの読み込みに失敗しました。');
				return false;
			}
			$this->setContent($row); //site_contentテーブルのフィールドの読み込み

			//下書き等の上書き
			//※すべてのデータを保持している分けではないので、リリースデータに上書き
			switch( $status ){
			case self::ST_DRAFT:
				$this->loadRevision(-1,$status);
				break;
			default:
			}
		}
		return true;
	}

	/*
	 * 下書きリソースの読み込み
	 *
	 * @param $id リソースID
	 * @return bool
	 *
	 */
	public function loadDraft($id){
		return $this->load($id,self::ST_DRAFT);
	}

	/*
	 * リソースの保存
	 *
	 * fieldを指定すれば特定のレコードだけ保存する
	 * (先頭に「tv.」をつけるとTVが対象)
	 *
	 * @param $fields		 Save target fields(blank or * = all)
	 * @param $clearCache Clear cache
	 * @return int/bool	 save id or false
	 *
	 */
	public function save($fields='*',$clearCache=true){
		$c = array(); //新規/更新対象content
		$tv = array(); //新規/更新対象tv

		$content = $this->getContent();
		if( empty($fields) || $fields == '*' ){
			foreach( $content as $key => $val ){
				if( !is_null($val) ){
					$c[$key] = $val;
				}
			}
			$tv = $this->tv;
		}else{
			if( !is_array($fields) )
				$fields = explode(',',$fields);
			foreach( $fields as $key ){
				if( isset($content[$key]) && !is_null($content[$key]) ){
					$c[$key] = $content[$key];
				}else{
					$tmp = $this->getTVName($key);
					if( $tmp !== false ){
						$tmp = $this->getTVID($tmp);
						$tv[$tmp] = $this->tv[$tmp];
					}else{
						$this->logWarn('Fields not exist:'.$key);
					}

				}
			}
		}

		//idは途中エラー時はfalseに変化
		$id = $this->getElementId();
		if( parent::isInt($id,1) ){
			if( !self::documentExist($id) ){
				$this->logerr('存在しないリソースIDを指定しています:'.$id);
				return false;
			}
		}else{
			$id = 0; //新規
		}

		// 日付調整
		foreach( $this->content_type_date as $val ){
			if( isset($c[$val]) && $c[$val] == 'now()' )
				$c[$val] = time();
		}

		//親リソース調整
		if( isset($c['parent']) ){ //nullの時に無視したいのであえてisset()を利用、同じような理由のif文が複数有
			if( !parent::isInt($c['parent'],0) ){
				$c['parent'] = 0;
			}
		}
		
		//メニューインデックス調整
		if( isset($c['menuindex']) ){
			if( $c['menuindex'] == 'auto' ){
				//自動採番
				if( $id != 0 && !array_key_exists('parent',$c) ){
					$rs = parent::$modx->db->select('parent','[+prefix+]site_content',"id=$id");
					if( $row = parent::$modx->db->getRow($rs) ){
						$pid = $row['parent'];
					}
				}elseif( isset($c['parent']) && !empty($c['parent']) ){
					$pid = $c['parent'];
				}else{
					$pid = 0;
				}
				$rs = parent::$modx->db->select('(max(menuindex) + 1) AS menuindex','[+prefix+]site_content',"parent=$pid");
				if( ($row = parent::$modx->db->getRow($rs)) && !empty($row['menuindex']) ){
					$c['menuindex'] = $row['menuindex'];
				}else{
					$c['menuindex'] = 0;
				}
			}elseif( !parent::isInt($c['menuindex'],0) ){
				$c['menuindex'] = 0;
			}
		}

		//content登録
		unset($c['id']);
		if( !empty($c) ){
			$c = parent::$modx->db->escape($c);

			if( $id != 0 ){
				//update
				if( !parent::$modx->db->update($c,'[+prefix+]site_content','id='.$id) )
					$id = false;
			}else{
				//insert
				$id = parent::$modx->db->insert($c,'[+prefix+]site_content');
			}
		}
		
		//TVの登録
		if( $id === false ){
			$this->logerr('contentの保存に失敗しているため、tvの保存は行いません。');
		}elseif( $id == 0 ){
			$this->logerr('新規リソースの場合は最初にリソースを保存する必要があります。');
			$id = false;
		}else{
			$errflag = false;

			$tmp='';
			foreach( $tv as $k => $v ){
				if( $v['value'] === $v['default'] ){
					//デフォルト時は削除
					if( parent::isInt($k,1) ){
						parent::$modx->db->delete('[+prefix+]site_tmplvar_contentvalues',
												"tmplvarid = $k AND contentid = $id");
					}
				}else{
					$rs	= parent::$modx->db->select('id','[+prefix+]site_tmplvar_contentvalues',"tmplvarid = $k AND contentid = $id");
					if( $row = parent::$modx->db->getRow($rs) ){
						$rs = parent::$modx->db->update(array( 'value' => parent::$modx->db->escape($v['value']) ),
														'[+prefix+]site_tmplvar_contentvalues',
														"tmplvarid = $k AND contentid = $id");
						if( !$rs ){
							$errflag = true;
						}
					}else{
						$rs = parent::$modx->db->insert(array( 'tmplvarid' => $k ,
															 'contentid' => $id ,
															 'value' => parent::$modx->db->escape($v['value'])
																							 ),
														'[+prefix+]site_tmplvar_contentvalues');
						if( !$rs ){
							$errflag = true;
						}
					}
				}
			}
		}
		if( $errflag ){
			$id = false;
		}
		
		if( $id !== false && $clearCache )
			parent::$modx->clearCache();
						
		return $id;
	}

	/*
	 * delete resource
	 *
	 * @param $clearCache Clear cache
	 * @return bool	 
	 *
	 */
	public function delete($clearCache=true){
		if( !parent::isInt($this->getElementId(),1) )
			return false;

		$this->set('deleted', 1);
		$this->set('deletedon', 'now()');
		//$this->content['deletedby'] = 1;
		return $this->save('deleted,deletedon',$clearCache);
	}

	/*
	 * undelete resource
	 *
	 * @param $clearCache Clear cache
	 * @return bool	 
	 *
	 */
	public function undelete($clearCache=true){
		if( !parent::isInt($this->getElementId(),1) )
			return false;

		$this->set('deleted', 0);
		$this->set('deletedon', '');
		//$this->content['deletedby'] = 1;
		return $this->save('deleted,deletedon',$clearCache);
	}

	//--- 以下はstaticメソッド
	/*
	 * リソースの存在確認
	 *
	 * 実際にリソースがあるか確認。
	 *
	 * @param $id リソースID
	 * @return bool	
	 *
	 */
	public static function documentExist($id){
		if( !parent::isInt($id,1) ){
			return false;
		}
		$rs	= parent::$modx->db->select('id','[+prefix+]site_content',"id = $id");
		if( $row = parent::$modx->db->getRow($rs) ){
			return true;
		}
		return false;
	}

	/*
	 * リソースの公開/非公開
	 *
	 * 対象リソースを公開/非公開にする。
	 * onPubを省略したら現在の状態を返す。
	 *
	 * @param $id リソースID
	 * @param $onPub 1…公開/0…非公開(true/falseでも可)
	 * @param $recursive trueの場合、子リソースも処理対象(デフォルト:false)
	 * @param $clearCache キャッシュクリアの有無
	 * @return 1/0/bool
	 *
	 */
	public static function chPublish($id,$onPub=null,$recursive=false,$clearCache=true){
		if( !self::documentExist($id) ){ return false; }

		if( is_null($onPub) ){
			//値の参照
			$rs	= parent::$modx->db->select('id,published','[+prefix+]site_content',"id = $id");
			if( $row = parent::$modx->db->getRow($rs) ){
				return $row['published'];
			}
			return false;
		}

		//値の更新
		$onPub = parent::bool2Int($onPub);
		$p = array();
		$p['published'] = $onPub;
		if( $onPub == 1 ){
			$p['publishedby'] = self::getLoginMgrUserID();
			$p['publishedon'] = time();
		}else{
			$p['publishedby'] = 0;
			$p['publishedon'] = 0;
		}

		$target = array();
		if( $recursive ){
			$target = self::getChildren($id);
		}
		$target[] = $id;
		$inList = '(' . implode(',',$target) . ')';

		if( parent::$modx->db->update( $p,'[+prefix+]site_content',"id IN $inList") ){
			if( $clearCache )
				parent::$modx->clearCache();
			return true;
		}
		return false;
	}

	/*
	 * リソースの削除/削除復活
	 *
	 * 対象リソースを削除、削除状態から復活させる
	 * onDelを省略したら現在の状態を返す。
	 *
	 * @param $id リソースID
	 * @param $onDel 1…削除/0…削除復活(true/falseでも可)
	 * @param $recursive trueの場合、子リソースも処理対象(デフォルト:true)
	 * @param $clearCache キャッシュクリアの有無
	 * @return 1/0/bool
	 *
	 */
	public static function chDelete($id,$onDel=null,$recursive=true,$clearCache=true){
		if( !self::documentExist($id) ){ return false; }

		if( is_null($onDel) ){
			//値の参照
			$rs	= parent::$modx->db->select('id,deleted','[+prefix+]site_content',"id = $id");
			if( $row = parent::$modx->db->getRow($rs) ){
				return $row['deleted'];
			}
			return false;
		}

		//値の更新
		$onDel = parent::bool2Int($onDel);
		$p = array();
		$p['deleted'] = $onDel;
		$addWhere = ''; //削除復活の場合、削除日が同じ子リソースを復活させる
		if( $onDel == 1 ){
			$p['deletedby'] = self::getLoginMgrUserID();
			$p['deletedon'] = time();
			$addWhere = '';
		}else{
			$p['deletedby'] = 0;
			$p['deletedon'] = 0;
			$rs	= parent::$modx->db->select('id,deletedon','[+prefix+]site_content',"id = $id");
			if( $row = parent::$modx->db->getRow($rs) ){
				$addWhere = "deletedon = {$row['deletedon']}";
			}
		}

		$target = array();
		if( $recursive ){
			$target = self::getChildren($id,$addWhere);
		}
		$target[] = $id;
		$inList = '(' . implode(',',$target) . ')';

		
		if( parent::$modx->db->update( $p,'[+prefix+]site_content',"id IN $inList") ){
			if( $clearCache )
				parent::$modx->clearCache();
			return true;
		}
	}

	/*
	 * リソースの完全削除
	 *
	 * DBからリソースを削除します。
	 * 削除フラグが落ちていると削除しません。
	 *
	 * @param $id リソースID
	 * @param $force trueの場合、強制削除(削除フラグ無視)(デフォルト:false)
	 * @param $recursive trueの場合、子リソースも削除(デフォルト:true)
	 * @param $clearCache Clear cache
	 * @return bool
	 *
	 */
	public static function erase($id,$force=false,$recursive=true,$clearCache=true){
		if( self::documentExist($id) ){
			if( !$force ){
				$rs	= parent::$modx->db->select('id,deleted','[+prefix+]site_content',"id = $id");
				if( ($row = parent::$modx->db->getRow($rs)) && $row['deleted'] != 1 ){
					return false;
				}
			}

			$target = array();
			if( $recursive ){
				$target = self::getChildren($id);
			}
			$target[] = $id;
			$inList = '(' . implode(',',$target) . ')';

			//tvの削除 -> content削除
			parent::$modx->db->delete('[+prefix+]site_tmplvar_contentvalues',"contentid IN $inList");
			$rs = parent::$modx->db->delete('[+prefix+]site_content',"id IN $inList");

			if( $rs !== false && $clearCache ){
				parent::$modx->clearCache();
			}
			return $rs;
		}
		return false;
	}

	//--- 以下はプライベートメソッド
	/*
	 * TV名を返す
	 *
	 * 先頭に「tv.」がある場合は削除される
	 * TV名ではない場合はfalseを返す
	 *
	 * @param $name 文字列
	 * @return string/false
	 *
	 */
	private function getTVName($name){
		$pos = strpos($name,'tv.');
		if( $pos === 0 ){
			$name = substr($name,3);
		}
		foreach( $this->tv as $k => $v ){
			if( $v['name'] == $name ){
				return $name;
			}
		}
		return false;
	}
										
	//--- Sub method (This method might be good to be another share class.)

	/*
	 * 子リソース一式を取得
	 *
	 * 指定したリソースIDの子リソース一覧を取得。
	 * 子の子(孫)も含めてすべて取得。
	 *
	 * @param $id リソースID
	 * @param $addWhere 追加条件式(※escapeしないので注意)
	 * @return リソースID郡
	 *
	 */
	private static function getChildren($id,$addWhere=''){
		$r = array();
		if( !empty($addWhere) ){
			$addWhere = "AND ( $addWhere )";
		}
		$ids = array($id);
		while( !empty($ids) ){
			$rs = parent::$modx->db->select('id','[+prefix+]site_content',"parent='".array_shift($ids)."' $addWhere");
			while( $row = parent::$modx->db->getRow($rs) ){
				array_push($ids,$row['id']);
				$r[] = $row['id'];
			}
		}
		return $r;
	}
}
