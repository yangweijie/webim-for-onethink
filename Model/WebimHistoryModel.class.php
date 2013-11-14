<?php
namespace Addons\Webim\Model;
use Think\Model;

class WebimHistoryModel extends Model {

	protected $tableName = 'webim_histories';

	public function get($uid, $with, $type='chat', $limit=30) {
		if( $type == "chat" ) {
			$where = "`type` = 'chat' AND ((`to`='$with' AND `from`='$uid' AND `fromdel` != 1)
					 OR (`send` = 1 AND `from`='$with' AND `to`='$uid' AND `todel` != 1))";
		} else {
			$where = "`to`='$with' AND `type`='grpchat' AND send = 1";
		}
		$rows = $this->where($where)->order('timestamp DESC')->limit(0, $limit)->select();

		file_put_contents('./webim.txt', var_export($rows, 1),FILE_APPEND);
		file_put_contents('./webim.txt', var_export($this->_sql(), 1),FILE_APPEND);
		// trace($rows);
		// trace($this->_sql());
		return array_reverse( $rows );
	}

	public function getOffline($uid, $limit = 50) {
		$rows = $this->where("`to`='$uid' and send != 1")->order('timestamp DESC ')->limit(0, $limit)->select();
		return array_reverse( $rows );
	}

	public function insert($user, $message) {
		$this->create($message);
		$this->from = $user->id;
		$this->nick = $user->nick;
		$this->created_at = date( 'Y-m-d H:i:s' );
		$this->add();
	}

	public function clear($uid, $with) {
		$this->where("from='$uid' and to='$with'")->save( array( "fromdel" => 1, "type" => "chat" ) );
		$this->where("to='$uid' and from='$with'")->save( array( "todel" => 1, "type" => "chat" ) );
		$this->where("todel=1 AND fromdel=1")->delete();
	}

	public function offlineReaded($uid) {
		$this->where("to='$uid' and send=0")->save(array("send" => 1));
	}

}

