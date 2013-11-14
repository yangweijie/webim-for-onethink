<?php
namespace Addons\Webim\Model;
use Think\Model;

class WebimSettingModel extends Model {

	protected $tableName = 'webim_settings';

	public function set($uid, $data, $type='web') {
		$setting = $this->where("uid='$uid'")->find();
		if( $setting ) {
			if ( !is_string( $data ) ){
				$data = json_encode( $data );
			}
			$setting[$type] = $data;
			$this->save($setting);
		} else {
			$setting = $this->create(array(
				'uid' => $uid,
				$type => $data,
				'created_at' => date( 'Y-m-d H:i:s' ),
			));
			$this->add();
		}
	}

	public function get($uid, $type = "web") {
		$setting = $this->where("uid='$uid'")->find();
		if($setting) {
			return json_decode($setting[$type]);
		}
		return new \stdClass();
	}

}
