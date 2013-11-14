<?php

namespace Addons\Webim;
use Common\Controller\Addon;

/**
 * WebIM插件
 * @author 无名
 */

    class WebimAddon extends Addon{
        public $custom_config = 'View/config.html';

        public $info = array(
            'name'=>'Webim',
            'title'=>'WebIM',
            'description'=>'基于Webim做的在线聊天插件',
            'status'=>0,
            'author'=>'yangweijie',
            'version'=>'0.1'
        );

        public function install(){
            if(!ini_get('allow_url_fopen')){
                session('addons_install_error', ',请先将php.ini中的allow_url_fopen开启');
                return false;
            }
            $db_prefix = C('DB_PREFIX');
            $sql = "CREATE TABLE IF NOT EXISTS `{$db_prefix}webim_histories` (
                        `id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
                        `send` tinyint(1) DEFAULT NULL,
                        `type` varchar(20) DEFAULT NULL,
                        `to` varchar(20) DEFAULT NULL,
                        `from` varchar(20) DEFAULT NULL,
                        `nick` varchar(20) DEFAULT NULL COMMENT 'from nick',
                        `body` text,
                        `style` varchar(150) DEFAULT NULL,
                        `timestamp` double DEFAULT NULL,
                        `todel` tinyint(1) NOT NULL DEFAULT '0',
                        `fromdel` tinyint(1) NOT NULL DEFAULT '0',
                        `created_at` date DEFAULT NULL,
                        `updated_at` date DEFAULT NULL,
                        PRIMARY KEY (`id`),
                        KEY `todel` (`todel`),
                        KEY `fromdel` (`fromdel`),
                        KEY `timestamp` (`timestamp`),
                        KEY `to` (`to`),
                        KEY `from` (`from`),
                        KEY `send` (`send`)
                    ) ENGINE=MyISAM;";
            D()->execute($sql);
            $sql = "CREATE TABLE IF NOT EXISTS `{$db_prefix}webim_settings` (
                        `id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
                        `uid` mediumint(8) unsigned NOT NULL,
                        `web` blob,
                        `air` blob,
                        `created_at` date DEFAULT NULL,
                        `updated_at` date DEFAULT NULL,
                        PRIMARY KEY (`id`)
                    ) ENGINE=MyISAM;";

            D()->execute($sql);
            $table_name_1 = "{$db_prefix}webim_histories";
            $table_name_2 = "{$db_prefix}webim_settings";
            $res = M()->query("SHOW TABLES LIKE '{$table_name_1}'");
            $count = count($res);
            $res = M()->query("SHOW TABLES LIKE '{$table_name_2}'");
            $count += count($res);
            if($count == 2){
                return true;
            }else{
                session('addons_install_error', ',部分表未创建成功，请手动检查插件中的sql，修复后重新安装');
                return false;
            }
        }

        public function uninstall(){
            $db_prefix = C('DB_PREFIX');
            $sql = "DROP TABLE IF EXISTS `{$db_prefix}webim_histories`;";
            D()->execute($sql);
            $sql = "DROP TABLE IF EXISTS `{$db_prefix}webim_settings`;";
            D()->execute($sql);
            return true;
        }

        //实现的pageFooter钩子方法
        public function pageFooter($param){
            $run = addons_url('Webim://Webim/run');
            echo <<<str
            <script type="text/javascript" src="{$run}"></script>
str;
        }
    }