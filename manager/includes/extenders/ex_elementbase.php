<?php
/*
 * API for Element base 
 *
 * リソースやスニペット制御用の基底Class
 *
 */

class ElementBase
{
	//リソースのステータス一覧
	const ST_NEW      = 'new';
	const ST_RELEASED = 'released';
	const ST_DRAFT    = 'draft';
	const ST_STANDBY  = 'standby';

	//ログレベル
	const LOG_INFO = 1;
	const LOG_WARN = 2;
	const LOG_ERR  = 3;

	const MAX_BUFF = 255; //文字列の最大長

	public static $modx=null; //MODXオブジェクトを指定しないとこのクラスは動作しません

	private $lastLog = ''; // Last log message

	private $APIName     = 'ElementBase';  //APIの名前
	private $logLevel    = self::LOG_ERR;  //Output log level
	private $elementType = 'resource';     //エレメントの種類
	private $status      = self::ST_NEW;   //状態
	private $elmid       = 0;              //エレメントID(保存時に必須)
	private $revid       = 0;              //変更履歴ID(保存時に必須)
	private $version       = 0;            //変更のバージョン
	private $description = '';             //概要
	private $content;                      //編集中のエレメントの内容。この変数は外部からのいかなる操作によってもフィールドの有無が変化しないように注意してください。
	private $pub_date    = 0;              //採用日
	
	/*
	 * __construct
	 *
	 * @param $name   継承先のクラスの名前
	 * @param $elm    エレメントの種類
	 * @param $id     リソースID(blank=New resource)
	 * @param $level ログレベル
	 * @return none
	 *
	 */
	public function __construct($name,$elm,$id=0,$level=''){
		$this->content = $this->getDefaultContent();

		if( !empty($name) )
			$this->APIName = $name;

		$this->elementType = $elm;

		if( self::isInt($id,1) ){
			$this->elmid = $id;
			$this->setStatus(self::ST_RELEASED);
		}

		if( self::isInt($level,1) )
			$this->logLevel = $level;
	}

	/*
	 * エレメントのすべてのフィールドに対応した既定の値のセットを取得します。
	 */
	protected function getDefaultContent(){
		return array();
	}

	/*
	 * エレメントID設定
	 *
	 * 連動してステータスも変更される。
	 *
	 * @param $id エレメントID(デフォルト0)
	 * @return bool
	 *
	 */
	public function setElementId($id=0){
		if( empty($id) ){
			$this->elmid = 0;
			$this->setStatus(self::ST_NEW);
		}else{
			if( self::isInt($id,1) ){
				$this->elmid = $id;
				if( $this->getStatus() == self::ST_NEW ){
					$this->setStatus(self::ST_RELEASED);
				}
			}else{
				$this->logWarn('IDの値が不正です。');
				return false;
			}

		}
		return true;
	}

	/*
	 * エレメントID取得
	 *
	 * @return int
	 *
	 */
	public function getElementId(){
		return $this->elmid;
	}

	/*
	 * ステータス設定
	 *
	 * ステータスを設定する
	 *
	 * @param $status エレメントのステータス
	 * @return bool
	 *
	 */
	public function setStatus($status){
		switch($status){
		case self::ST_NEW:
			if( $this->elmid == 0 ){
				$this->status = $status;
				return true;
			}else{
				$this->logWarn('エレメントIDが設定されているのでステータスはNEWに変更できません。');
			}
			break;			
		case self::ST_RELEASED:
		case self::ST_DRAFT:
		case self::ST_STANDBY:
			if( self::isInt($this->elmid,1) ){
				$this->status = $status;
				return true;
			}else{
				$this->logWarn('エレメントIDが設定されていません。');
			}
			break;
		}
		return false;
	}

	/*
	 * ステータス取得
	 *
	 * ステータスを取得する
	 *
	 * @return string
	 *
	 */
	public function getStatus(){
		return $this->status;
	}

	/*
	 * 概要設定
	 *
	 * @param $str 概要文
	 * @return bool
	 *
	 */
	public function setDescription($str=''){
		if( mb_strlen($str) > self::MAX_BUFF ){
			$this->logWarn('Descriptionが長すぎます。最大文字長:'.self::MAX_BUFF);
			return false;
		}
		$this->description = $str;
		return true;
	}

	/*
	 * 概要取得
	 *
	 * @return string
	 *
	 */
	public function getDescription(){
		return $this->description;
	}

	/*
	 * 現在のエレメントから、指定されたフィールドの値を取得します。
	 *
	 * @param $field 取得するフィールドの名前。
	 * @return string このメソッドは指定された名前のフィールドが存在した場合、対応する現在の値を返します。それ以外の場合はfalseを返します。
	 *
	 */
	public function get($field){
		if( !empty($field) && array_key_exists($field,$this->content) ){
			return $this->content[$field];
		}
		return false;
	}

	/*
	 * 現在のエレメントに指定されたフィールドの値を上書きします。
	 * このメソッドは未知のフィールドが指定された場合、警告を記録して新しい値を無視します。
	 *
	 * @param $field 更新するフィールドの名前。
	 * @param $val 指定されたフィールドの新しい値。
	 * @return bool このメソッドは更新に成功したかどうかにかかわらず、常にtrueを返します。
	 *
	 */
	public function set($field,$val){
		if( array_key_exists($field,$this->content) ){
			$this->content[$field] = $val;
			return true;
		}
		$this->logWarn('Field not exist:'.$field);
		return true;
	}

	/*
	 * 指定された配列でコンテンツを完全に更新します。パラメータで指定されなかったフィールドには既定の値が適用されます。
	 * このメソッドは指定されたコンテンツに予期しないフィールドが含まれる場合はその値をすべて無視し、警告を記録します。
	 *
	 * @param $content 更新するコンテンツの新しい値のセット。
	 * @return bool このメソッドは更新に成功したかどうかにかかわらず、常にtrueを返します。
	 *
	 */
	public function setContent($content=array()){
		$this->content = $this->getDefaultContent();
		if (isset($content)) {
			foreach ( $content as $key => $val ) {
				$this->set($key, $val);
			}
		}
		return true;
	}

	/*
	 * 現在のエレメントの内容をすべて取得します。
	 *
	 * @return array/false
	 *
	 */
	public function getContent(){
		return $this->content;
	}

	/*
	 * 公開(採用)日設定
	 *
	 * ステータスも同時に修正されます。
	 * 0の場合、公開日がリセットされます。
	 *
	 * @param $date 公開(採用)日
	 * @return bool
	 *
	 */
	public function setPubDate($date=0){
		if( empty($date) ){
			$this->pub_date = 0;
			$this->setStatus(self::ST_NEW);
		}else{
			if( self::isInt($date,1) ){
				$this->pub_date = $date;
				$this->setStatus(self::ST_STANDBY);
			}else{
				$this->logWarn('公開(採用)日の値が不正です。');
				return false;
			}
		}
		return true;
	}

	/*
	 * 公開日取得
	 *
	 * フォーマット指定があると親切かも。
	 *
	 * @return int
	 *
	 */
	public function getPubDate(){
		return $this->pub_date;
	}

	/*
	 * 現在のエレメントの新しいリビジョンを保存します。
	 * バージョン番号は保存されているバージョン番号の最大値+1となります。
	 *
	 * @param $status 保存されるリビジョンのステータス。指定しなければ現在の状態が適用されます。
	 * @return bool このメソッドはリビジョンの追加に成功した場合、trueを返します。それ以外の場合はfalseを返します。
	 *
	 */
	public function addRevision($status = null){
		$f = $this->getRevisionFields();
		if (!empty($status)) {
			$f['status'] = $status;
		}
		$max_ver = self::$modx->db->select('COALESCE(MAX(version),0)','[+prefix+]site_revision',"element='{$this->elementType}' AND elmid={$this->elmid}");
		if ($max_ver !== false) {
			$max = $max_ver->fetch_row();
			$f['version'] = $max[0] + 1;
			$max_ver->free();
			unset($f['internalKey']); //新規作成のため必要ない
			if(self::$modx->db->insert($f, '[+prefix+]site_revision') === true){
				$this->revid = self::$modx->db->insert_id;
				$this->status = $f['status'];
				$this->version = $f['version'];
				return true;
			}
		}
		return false;
	}

	/*
	 * 現在のエレメントの内容を変更履歴に保存します。
	 * このメソッドはエレメントそのものの状態を更新しません。
	 *
	 * @param $status 保存されるリビジョンのステータス。規定値は下書き(draft)です。
	 * @return bool このメソッドはリビジョンの追加に成功した場合、trueを返します。それ以外の場合はfalseを返します。
	 *
	 */
	public function saveRevision(){
		if (!empty($this->revid)) {
			return self::$modx->db->update($this->getRevisionFields(), '[+prefix+]site_revision', "internalKey = {$this->revid}");
		}
		return false;
	}

	/*
	 * このオブジェクトの状態をrevisionテーブルの保存形式に変換して取得します。
	 */
	protected function getRevisionFields(){
		return array(
			'internalKey' => $this->revid,
			'element' => $this->elementType,
			'elmid' => $this->elmid,
			'version' => $this->version,
			'status' => ($status == 'inherit') ? $this->status : $status,
			'description' => $this->description,
			'content' => serialize($this->content),
			'editedon' => time(),
			'editedby' => self::getLoginMgrUserID(),
			'submittedon' => 0,
			'submittedby' => 0,
			'approvedon' => 0,
			'approvedby' => 0,
			'pub_date' => $this->pub_date
			);
	}

	/*
	 * 指定されたバージョンのリビジョンを読み込みます。
	 *
	 * @param $version 読み込むリビジョンのバージョン番号。負の数値を指定すると最も大きいバージョンからさかのぼって取得します。
	 * @param $status 読み込むリビジョンのステータス。'*'を指定した場合、このパラメータは無視されます。規定値は'*'です。
	 * @return bool このメソッドはリビジョンの読み込みに成功した場合、trueを返します。それ以外の場合はfalseを返します。
	 *
	 */
	public function loadRevision($version,$status='*'){
		$v = intval($version);
		$table = self::$modx->db->replaceFullTableName('[+prefix+]site_revision');
		if ($v < 0){
			$offset = -1 - $v;
			$v = <<<SQL
(SELECT MAX(latest.version) - {$offset} FROM {$table} AS latest WHERE latest.element = '{$this->elementType}' AND latest.elmid = {$this->elmid} GROUP BY latest.elmid)
SQL;
		}
		$where = "version = {$v} AND element = '{$this->elementType}' AND elmid = {$this->elmid}";
		if ($status != '*') {
			$where .= " AND status = '{$status}'";
		}
		$revision = self::$modx->db->select('*', $table, $where, 'version DESC');
		if ($revision !== false) {
			$row = $revision->fetch_assoc();
			extract($row);
			$revision->free();
			$this->revid = $internalKey;
			$this->version = $version;
			$this->status = $status;
			$this->description = $description;
			$this->pub_date = $pub_date;
			$this->setContent(unserialize($content));
		
			return true;
		}
		return false;
	}

	/*
	 * 指定されたバージョンのリビジョンを削除します。
	 *
	 * @param $version 削除するリビジョンのバージョン番号の条件。配列で指定すると指定されたすべてのバージョンが削除されます。
	 * @param $status 削除するリビジョンのステータス。'*'を指定した場合、このパラメータは無視されます。規定値は'*'です。
	 * @return bool このメソッドはリビジョンの削除に成功した場合、trueを返します。それ以外の場合はfalseを返します。
	 *
	 */
	public function eraseRevision($version,$status='*'){
		$v = intval($version);
		$table = self::$modx->db->replaceFullTableName('[+prefix+]site_revision');
		if (is_array($version)){
			$expr_ver = 'IN('.implode(',', $version).')';
		}
		else {
			$expr_ver = "= {$version}";
		}
		$where = "version {$expr_ver} AND element = '{$this->elementType}' AND elmid = {$this->elmid}";
		if ($status != '*') {
			$where .= " AND status = '{$status}'";
		}
		return self::$modx->db->delete($table, $where);
	}

	/*
	 * このエレメントのリビジョンの一覧をバージョン番号順に取得します。同一のバージョン番号が存在した場合、該当するリビジョンは追加された順番に並べられます。
	 *
	 * @param $count リビジョンリストの取得件数を指定します。指定しなかった場合は制限されません。このパラメータが負の値の場合は0とみなされます。
	 * @param $offset 出力するリビジョンリストの開始位置を指定します。負の数値を指定すると-1で最新のリビジョン、ついでさらに古いリビジョンへとさかのぼります。規定値は-1です。
	 * @param $date_from リビジョンの取得開始日を指定します。規定値は0です。
	 * @param $date_to リビジョンの取得開始日を指定します。このパラメータは有効な数値を指定しなかった場合、現在の時刻が適用されます。規定値はnullです。
	 * @return mixed このメソッドはリビジョン情報の取得に成功した場合、バージョン番号(version)と説明(description)を含んだ連想配列の配列を返します。それ以外の場合はfalseを返します。
	 *
	 */
	function getRevisionList($count=null,$offset=-1,$date_from=0,$date_to=null){
		$where = "element='{$this->elementType}' AND elmid={$this->elmid}";
		if (isset($count) && self::isInt($count)) {
			$limit = $count <= 0 ? '0' : "{$count}";
		}
		else {
			$limit = '18446744073709551615'; //MySQLのLIMIT句はALLを指定できない
		}
		if ($offset < 0) {
			$numrevs = self::$modx->db->select('COUNT(internalKey)','[+prefix+]site_revision', $where);
			if ($numrevs !== false) {
				$count = $numrevs->fetch_row();
				$offset += $count[0];
				$limit .= " OFFSET {$offset}";
				$last->free();
			}
		}
		else {
			$limit .= " OFFSET {$offset}";
		}
		$result = self::$modx->db->select(
			'version,status,description',
			'[+prefix+]site_revision',
			$where,
			'version',
			$limit
			);
		if ($result !== false) {
			$list = array();
			do {
				$row = $result->fetch_assoc();
				if (isset($row)) {
					$list[] = $row;
				}
				else {
					break;
				}
			} while (true);
			return $list;
		}
		return false;
	}

	/*
	 * このエレメントの指定されたリビジョンの情報を取得します。
	 *
	 * @param $version 取得するリビジョンのバージョン番号。指定しなかった場合は現在のバージョンの最後の保存分を取得します。
	 * @return mixed このメソッドはリビジョン情報の取得に成功した場合、条件にマッチする最初のリビジョンのすべての項目を含んだ連想配列を返します。それ以外の場合はfalseを返します。
	 *
	 */
	function getRevisionInfo($version=null)
	{
		if (!isset($version)) {
			$version = $this->version;
		}
		if (self::isInt($version)) {
			$result = self::$modx->db->select('*','[+prefix+]site_revision',"element='{$this->elementType}' AND elmid={$this->elmid} AND version={$version}");
			$row = $result->fetch_assoc();
			$result->free();
			if (isset($row)) {
				return $row;
			}
		}
		return false;
	}

	/*
	 * このオブジェクトの処理中に発生した最後のログメッセージを取得します。
	 *
	 * @return string このメソッドはこのオブジェクトに関係するもっとも最近記録されたログ情報を返します。
	 *
	 */
	public function lastLog(){
		return $this->lastLog;
	}
	
	/*
	 * logging / loginfo / logwarn / logerr
	 *
	 * @param level Log level
	 * @param msg Log massages
	 * @return bool   
	 *
	 */
	protected function logging($level,$msg=''){
		$this->lastLog = $msg;
		if( $this->logLevel <= $level )
			self::$modx->logEvent(4,$level,$msg,$this->APIName);
	}
	
	protected function loginfo($msg=''){
		$this->logging(self::LOG_INFO,$msg);   
	}
	
	protected function logwarn($msg=''){
		$this->logging(self::LOG_WARN,$msg);   
	}
	
	protected function logerr($msg=''){
		$this->logging(self::LOG_ERR,$msg);   
	}

	//--- Static function
									  
	//--- Sub method (This method might be good to be another share class.)
	/*
	 * ログインユーザIDを取得
	 *
	 * $modx->getLoginUserID()のラッパー
	 * 管理ユーザ専用とし、falseを返した際に0を返すように変更
	 *
	 * @param なし
	 * @return ユーザ名ID
	 *
	 */
	protected static function getLoginMgrUserID(){
		$u = self::$modx->getLoginUserID('mgr');
		if( empty($u) ){
			return 0;
		}
		return $u;
	}

	/*
	 * Number check
	 *
	 * @param $param Input data
	 * @param $min   Minimum value
	 * @param $max   Maximum value
	 * @return bool
	 *
	 */
	protected  static function isInt($param,$min=null,$max=null){
		if( !preg_match('/\A[0-9]+\z/', $param) ){
			return false;
		}
		if( !is_null($min) && preg_match('/\A[0-9]+\z/', $min) && $param < $min ){
			return false;
		}
		if( !is_null($max) && preg_match('/\A[0-9]+\z/', $max) && $param > $max ){
			return false;
		}
		return true;
	}  

	/*
	 * bool型をIntに変換
	 *
	 * DBに登録できるようboolを0/1に変換。
	 * $paramに1/0が渡ってきた場合はそのまま返す。
	 * 認識できない$paramはすべて 0 とする。
	 *
	 * @param $param bool or 0/1
	 * @return 0/1
	 *
	 */
	protected static function bool2Int($param){
		if( $param === true || $param == 1 ){
			return 1;
		}
		return 0;
	}

}
