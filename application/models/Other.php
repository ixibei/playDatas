<?php
/**
*   杂项
*/
class OtherModel extends Db_Base
{

    public function __construct($language='')
    {
        parent::__construct();
    }

    /**
    * 赞或者啋
    */
    public function handleLike($sql,$packageName)
    {
        if($this->_db->query($sql))
        {
            $this->redis->select(7);
            if($jsonData = $this->redis->get('appboxD_' . $packageName . '_' . $this->language)) {//重置赞啋数
                $dataArr = json_decode($jsonData,true);
                $sql = "select likeCount,hateCount from appbox_app where package_name='$packageName' and
                language='{$this->language}'";
                $count = $this->_db->getRow($sql);
                $dataArr['data']['treadCount'] = $count['hateCount'];
                $dataArr['data']['praiseCount'] = $count['likeCount'];
                $this->redis->getSet('appboxD_' . $packageName . '_' . $this->language,json_encode($dataArr));
            }
            return array('status'=>1,'info'=>'设置成功！');
        }
        else
        {
            return array('status'=>0,'info'=>'设置失败！');
        }
    }

    /**
    *   是否更新
    */
    public function getUpdate($giftUpdateTime,$spreadUpdateTime,$dayilyUpdateTime,$ver_code)
    {
        if(!$ver_code) {
            echo '参数错误！';exit;
        }
        $arr = array('status'=>1);//返回的json

        //精选有无更新
        $sql = "select count(*) as num from appbox_dayily where addTime>$dayilyUpdateTime and status=1 and releaseTime<".time();
        $dayily = $this->_db->getAll($sql);
        $arr['update']['dayily'] = $dayily[0]['num'] ? $dayily[0]['num'] : 0;

        //专题有无更新
        $sql = "select count(*) as num from appbox_spread where addTime>$spreadUpdateTime and status=1 and releaseTime<".time();
        $spread = $this->_db->getAll($sql);
        $arr['update']['spread'] = $spread[0]['num'] ? $spread[0]['num']: 0;

        //礼包有无更新
        $sql = "select count(*) as num from appbox_gift where add_time>$giftUpdateTime and status=1 and start_time<".time();
        $gift = $this->_db->getAll($sql);
        $arr['update']['gift'] = $gift[0]['num'] ? $gift[0]['num'] : 0;

        //通知内容
        $sql = "select app.package_name as id,typeName as title from appbox_announce as an left join appbox_app as app on app.package_id=an.typeId where an.addTime>$dayilyUpdateTime and an.type='app' and an.status=1 and app.language='".$this->language."' order by an.status desc,an.sort desc,an.id asc";
        $app = $this->_db->getRow($sql);//应用游戏

        if($app)
        {
            $app['content'] = '应用'.$app['title'].'有了更新！';        
            $arr['notice']['app'][] = $app;
        }

        $sql = "select typeId as id,typeName as title from appbox_announce where addTime>$spreadUpdateTime and type='spread' and status=1 order by status desc,sort desc,id asc";
        $spread = $this->_db->getRow($sql);//专题
        if($spread)
        {
            $spread['content'] = '专题'.$spread['title'].'有了更新！';
            $arr['notice']['subject'][] = $spread;
        }

        $sql = "select typeId as id,typeName as title from appbox_announce where addTime>$giftUpdateTime and type='gift' and status=1 order by status desc,sort desc,id asc";
        $gift = $this->_db->getAll($sql);//礼包
        if($gift)
        {
            $count = count($gift);
            if($count > 2)
            {
                $data['id'] = 0;
                $data['title'] = '礼包有了更新！';
                $data['content'] = '礼包有了'.$count.'条更新！';
                $arr['notice']['gift'][] = $data;
            }  
            else
            {
                foreach($gift as $key=>$val)
                {
                    $gifts['id'] = $val['id'];
                    $gifts['title'] = $val['title'];
                    $gifts['content'] = '礼包'.$val['title'].'有了更新！';
                    $arr['notice']['gift'][] = $gifts;
                }
            }
        }

        //检查自身版本是后否有更新
        $sql = "select max(versionCode) as id from appbox_update_version where status=1";
        $maxVersionCode = $this->_db->getRow($sql);
        if($maxVersionCode['id'] > $ver_code) {
            $sql = "select * from appbox_update_version where versionCode=".$maxVersionCode['id'];
            $data = $this->_db->getRow($sql);
            $arr['appUpdate']['hasNew'] = true;
            $arr['appUpdate']['versionCode'] = $data['versionCode'];
            $arr['appUpdate']['dialogContent'] = $data['dialogContent'];
            $arr['appUpdate']['downloadUrl'] = $data['downloadUrl'];
            $arr['appUpdate']['title'] = $data['title'];
            $arr['appUpdate']['content'] = $data['content'];
            $arr['appUpdate']['forcing'] = $data['force'];
        }            
        return json_encode($arr);
    }

/**
*   添加取消收藏
*/
    public function getFavorite($uuid,$packageName,$cancel)
    {
        if($cancel == 1)//取消收藏
        {
            $sql = "delete from appbox_user_favorite where uuid=$uuid and packageName='$packageName'";
            if($this->_db->query($sql))
                return json_encode(array('status'=>1,'info'=>'取消收藏成功!~'));
            else
                return json_encode(array('status'=>$this->is_true,'info'=>'取消收藏失败!~'));
        }
        $sql = "insert into appbox_user_favorite values('$uuid','$packageName')";
        if($this->_db->query($sql))
            return json_encode(array('status'=>1,'info'=>'添加收藏成功!~'));
        else
            return json_encode(array('status'=>0,'info'=>'添加收藏失败!~'));
    }
/**
*   获取关键词
*/
    public function getKeywords()
    {
        $arr = array('status'=>1);
        //自定义关键词
        $sql = "select keywords from appbox_keywords where status=1 order by sort desc";
        $data = $this->_db->getAll($sql);
        if(!$data) return json_encode(array('status'=>$this->is_true));
        $temp = array();
        foreach($data as $key=>$val)
        {
            $temp[] = $val['keywords'];
        }
        $arr['data'] = $temp;
        //从googleplay抓取过来的关键词
        $this->redis->select(0);
        if(!$this->redis->exists('appbox_keywords')) {
            file_get_contents('http://play.mobappbox.com/index.php?m=Admin&c=ApplicationRepertory&a=annie&type=keywords&flag=1');
        }
        $keywords = $this->redis->get('appbox_keywords');
        $keywords = json_decode($keywords,true);
        $currentKeywords = isset($keywords[strtoupper($this->language)]) ? $keywords[strtoupper($this->language)] : $keywords['en'];
        $value = array_rand($currentKeywords,5);
        foreach($value as $val) {
            $arr['keywords'][] = $currentKeywords[$val];
        }
        //print_R($arr);exit;
        return json_encode($arr);
    }

    //意见反馈接口
    public function setFeedback()
    {
        $uuid = isset($_REQUEST['uuid']) ? $_REQUEST['uuid'] : '';
        $feedback = isset($_REQUEST['feedback']) ? $_REQUEST['feedback'] : '';
        $language = isset($_REQUEST['language']) ? $_REQUEST['language'] : '';
        $country = isset($_REQUEST['country']) ? $_REQUEST['country'] : '';
        $android_version = isset($_REQUEST['android_version']) ? $_REQUEST['android_version'] : '';
        $manufacture = isset($_REQUEST['manufacture']) ? $_REQUEST['manufacture'] : '';
        $ver_code = isset($_REQUEST['ver_code']) ? $_REQUEST['ver_code'] : '';
        $model = isset($_REQUEST['model']) ? $_REQUEST['model'] : '';
        $email = isset($_REQUEST['email']) ? $_REQUEST['email'] : '';

        if(!$uuid || !$feedback || !$language || !$country || !$android_version || !$manufacture || !$ver_code || !$model)  {
            echo '参数错误！';
        }     
        $sql = "insert into appbox_feedback values('','$uuid','$email','$language','$country','$android_version','$ver_code','$manufacture','$model','".time()."','$feedback')";
        $is_true = $this->_db->query($sql);
        if($is_true) {
            return json_encode(array('info'=>'留言成功！','status'=>1));
        } else {
            return json_encode(array('info'=>'留言失败，请稍后重试！','status'=>$this->is_true));
        }

    }
}