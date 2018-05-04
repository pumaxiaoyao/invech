<?php
namespace app\v1\controller;

use think\Db;
use think\Session;
use app\live\agGame;
use app\live\mgGame;
use app\live\bbingame;
use app\live\ptGame;
use app\live\sunbet;
use app\live\ptGamePlayer;
use app\live\oggame;
use think\Request;
use app\v1\Base ;
use app\v1\Login;

class User extends Login{

    const STATUS_SUCCESS = 0 ; //数据请求成功返回状态码
    const STATUS_ERROR   = 1 ; //数据请求失败返回状态码

    public function info(){
        $uid = session('uid');
        $user = db('k_user')->where('uid',$uid)->field('ask,answer,username,money,password,pay_card,pay_name,pay_num,pay_address,qk_pwd')->find();
        return $user;
    }
        
    public function get_money(){ //申请提现
    	$uid = Session::get('uid');
    	$user = Db::table('k_user')->where(array('uid'=>$uid))->find();
    	if($user['pay_card']=='' || $user['pay_num']=='' || $user['pay_address']==''){
    	    message("请先设置您的财务资料在进行操作","/user/Set_Card");
    	}
		if($_POST){
		    
            if(request()->isMobile()){
                if(!captcha_check($_POST['vlcodes'], "mobile_get_money")){
                    message("验证码错误!");
                }
            }
			$payvalue = trim(doubleval($_POST["pay_value"]));
			Db::startTrans();//开启事务
			$user = Db::table('k_user')->lock(true)->where(array('uid'=>$uid))->find();
			if($user['money'] < $payvalue){
			    Db::rollback();
				message("取款金额不能大于账户余额!");
			}
			$qkpwd = md5(trim($_POST['qk_pwd']));
			if($qkpwd!=$user['qk_pwd']){
			    Db::rollback();
				message("资金密码不正确!");
			}
			//当天提款次数
			$date_s = date("Y-m-d")." 00:00:00";
			$date_e = date("Y-m-d")." 23:59:59";
			$where = ' uid = ' .$user['uid']. ' and status=2 and m_value<0 and m_make_time >'."'$date_s'".' and m_make_time <'."'$date_e'";		
			$count = Db::table('k_money')->where($where)->count();//当天提款次数
			
			if($count>=3){
			    Db::rollback();
				message("您的本次提款申请失败，由于银行系统管制，每个帐号每天限制只能提款3次。");
			}
			
			try {
				$pay_value =	0-$payvalue; //把金额置成带符号数字
				$order = date("YmdHis")."_".$user['username'];
				$value = $user['money'] - $payvalue;
				$data_user['money'] = $value;
				$data['uid'] = $user['uid'];
				$data['m_value'] = $pay_value;
				$data['status'] = 2;
				$data['m_order'] = $order;
				$data['pay_card'] = $user['pay_card'];
				$data['pay_num'] = $user['pay_num'];
				$data['pay_address'] = $user['pay_address'];
				$data['type'] = '900';
				$data['pay_name'] = $user['pay_name'];
				$data['about'] = '';
				$res = Db::table('k_user')->where('uid','=',$user['uid'])->update($data_user);
				$sign = Db::table('k_money')->insert($data);
				
				Db::commit();  //事务成功    
				message("提款申请已经提交，等待财务人员给您转账。\\n您可以到历史报表中查询您的取款状态！","/user/data_t_money");
			}catch(Exception $e){
				Db::rollback();  //数据回滚
				message("由于网络堵塞，本次申请提款失败。\\n请您稍候再试，或联系在线客服。");
			}
		}
		
    	$this->assign('user',$user); 
    	return $this->fetch('get_money');
    }
    
    public function set_card(){ //设置收款信息
    	$uid = Session::get('uid');
    	$user = Db::table('k_user')->where(array('uid'=>$uid))->find();
    	if(isset($_POST['vlcodes'])){
    	    if(!captcha_check(input('vlcodes'))){
    	        $this->error('验证码错误!');
    	    }
    	}
    	
    	if($_POST){
    	    $paycard = trim(input('pay_card'));
    	    $paynum= trim(input('pay_num'));
    		if(!is_numeric($paynum)){
    			message("银行账号必须为数字");
    		}
    		if($this->request->isMobile()){
    		    $address_1 = trim(input('add1'));
    		    $address_2 = trim(input('add2'));
    		    $address_3 = trim(input('add3'));
    		    $address = $address_1.$address_2.$address_3;
    		    $moneypass = trim(input('qk_pwd'));
    		}else{
    		    $address_1 = trim(input('Address_1'));
    		    $address_2 = trim(input('Address_2'));
    		    $address_3 = trim(input('Address_3'));
    		    $address_4 = trim(input('Address_4'));
    		    $address = $address_1.$address_2.$address_3.$address_4;
    		    $moneypass = trim(input('MoneyPass'));
    		}
    		
    		
    		
    		if($user['qk_pwd']!=md5($moneypass)){
    			echo "<script>alert('资金密码错误');location.href='/user/set_card';</script>";exit();
    		}
     		$data = array(
    				'pay_card'=>$paycard,
    				'pay_num'=>$paynum,
    				'pay_address'=>$address,
    		);
    		$update = Db::table('k_user')->where('uid','=',$user['uid'])->update($data);
    		if($update==true){
    			echo "<script>alert('收款银行设置成功');location.href='/user/get_money';</script>";exit();
    		}else{
    			echo "<script>alert('收款银行设置失败');location.href='/user/userinfo';</script>";exit();
    		}
    	}
    	$this->assign('user',$user);  
    	return $this->fetch('set_card');
    }
    
    public function set_money(){ //账户充值
    	$uid = Session::get('uid');
    	$user = Db::table('k_user')->where(array('uid'=>$uid))->find();
    	
    	$webpay = Db::table('web_pay')->where('ok','=','1')->select();
    	
    	$this->assign('webpay',$webpay);
    	$this->assign('user',$user);
    	return $this->fetch('set_money');
    }
    
    
    public function huikuan_new(){
        $cards = Db::table('huikuan_bank')->select();
        $list = array();
        foreach ($cards as $v){
            $list[$v['card_group']]['cards'][] = $v;
        }
        $bank = $list[Session('gid')]['cards'] ?$list[Session('gid')]['cards'] : [];
        $this->assign('bank',$bank);
        return $this->fetch();
    }
    
    public function huikuan_form(){
        $id = $this->request->param('id');
        var_dump($id);
        $gid = Session('gid');
        $info = Db::table('huikuan_bank') -> where('id','eq',$id)->where('card_group','eq',$gid)->find();
        $this->assign('info',$info);
        $cards = Db::table('huikuan_bank')->select();
        $list = array();
        foreach ($cards as $v){
            $list[$v['card_group']]['cards'][] = $v;
        }
        $bank = $list[Session('gid')]['cards'] ? $list[Session('gid')]['cards'] :[];
        $this->assign('bank',$bank);
        return $this->fetch();
    }
    
    public function hk_money(){
        $cards = Db::table('huikuan_bank')->select();
        $list = array();
        foreach ($cards as $v){
            $list[$v['card_group']]['cards'][] = $v;
        }
        $bank = $list[Session('gid')]['cards'] ?$list[Session('gid')]['cards'] : [];
        $this->assign('bank',$bank);
        return $this->fetch();
    }
    
    public function huikuan_2($id=0){
        $id = intval($id);
        $gid = Session('gid');
        $info = Db::table('huikuan_bank') -> where('id','eq',$id)->where('card_group','eq',$gid)->find();
        $this->assign('info',$info);
        return $this->fetch();
    }
    
    public function hk_money_set(){
        date_default_timezone_set('PRC');
        $uid =Session('uid');
        $param = $this->request->param();
        if($param['into']){
            try{
                Db::startTrans();
                $assets = Db::table('k_user')->where('uid','eq',$uid)->field(['money'])->limit('1')->find();
                $money = $param['v_amount'];
                $bank = $param['IntoBank'];
                $date = $param['cn_date'];
                $date1 = $date." ".$param["s_h"].":".$param["s_i"].":00";
                $manner = $param['InType'];
                $address = $param['v_site'];
                if($manner == '网银转账'){
                    $manner .= '<br/> 持卡人姓名'.$param['v_Name'];
                }
                if($manner == "0"){
                    $manner = $param['IntoType'];
                }
                $tmpdate = date("Y-m-d H:i:s");
                $data['assets'] = $assets;
                $data['money'] = $money;
                $data['bank'] = $bank;
                $data['date'] = $date;
                $data['manner'] = $manner;
                $data['address'] = $address;
                $data['adddate'] = $tmpdate;
                $data['status'] = 0;
                $data['uid'] = $uid;
                $data['lsh'] = Session('username').'_'.date("YmdHis");
                $data['balance'] = $assets;
                Db::table('huikuan')->insert($data);
                Db::commit();
                $this->success("恭喜您，汇款信息提交成功。我们将尽快审核，谢谢您对我们的支持。");
            }catch(\think\Exception $e){
                Db::rollback();
                $this->error('系统错误:'.$e->getMessage().',您提交的转账信息失败!');
            }
        }
    }
    
    public function userinfo(){ //额度转换
    
        date_default_timezone_set('PRC');
    	$uid = session('uid');
    	$user = db('k_user')->where('uid',$uid)->find();
    	$userinfo = $user;
    	
    	//用户名
    	$username  = session('username');
    	$password = substr(md5(md5($username)),3,12);
    	
    	if (mb_substr($username,-3,3,'utf-8') != 'hga') {
    	    $temp_username = $username . 'hga';
    	} else {
    	    $temp_username = $username;
    	}
    	$mg_username = $username.'@hga';
    	
    	if(request()->isGet()){
    	    //维护信息
    	    $agWeihu = $naWeihu = $mgWeihu = $bbinWeihu = $ptWeihu = $ogWeihu = $ntWeihu = $sbWeihu = 0;
    	    $fengpans = db('k_fengpan')->select();
    	    foreach($fengpans as $fengpan){
    	        switch ($fengpan['name']){
    	            case 'agzr':
    	                $agWeihu = $fengpan['weihu'];
    	            case 'nazr':
    	                $naWeihu = $fengpan['weihu'];
    	            case 'mgzr':
    	                $mgWeihu = $fengpan['weihu'];
    	            case 'bbzr':
    	                $bbinWeihu = $fengpan['weihu'];
    	            case 'ptzr':
    	                $ptWeihu = $fengpan['weihu'];
    	            case 'ogzr':
    	                $ogWeihu = $fengpan['weihu'];
    	            case 'ntzr':
    	                $ntWeihu = $fengpan['weihu'];
    	        }
    	    }
    	    $this->assign('agWeihu',$agWeihu);
    	    $this->assign('naWeihu',$naWeihu);
    	    $this->assign('mgWeihu',$mgWeihu);
    	    $this->assign('bbinWeihu',$bbinWeihu);
    	    $this->assign('ptWeihu',$ptWeihu);
    	    $this->assign('ogWeihu',$ogWeihu);
    	    $this->assign('ntWeihu',$ntWeihu);
    	    $this->assign('sbWeihu',$sbWeihu);//表中没有申博的
    	        	    
    	    $this->assign('temp_username',$temp_username);
    	    $this->assign('mg_username',$mg_username);
    	    
    	    $this->assign('user',$user);
    	    $this->assign('userinfo',$userinfo);

            if(request()->isMobile()){
               // $balance_ag = $this->balance('agzr');
               // $balance_bb = $this->balance('bbzr');
                //$balance_og = $this->balance('ogzr');
                //$balance_ag = $this->balance('agzr');
                //$balance_mg = $this->balance('mgzr');//代处理
                //$balance_sb = $this->balance('sbzr');//处理中
                //$balance_pt = $this->balance('ptzr');
                $balance_bb = 0;//$balance_bb['money'];
                $balance_og = 0;//$balance_og['money'];
                $balance_ag = 0;//$balance_ag['money'];
                $balance_mg = 0;//$balance_mg['money'];
                //$balance_sb = $balance_sb['money'];
                $balance_pt = 0;//$balance_pt['money'];
                /*
                $balance_bb = '0.00';
                $balance_og = '0.00';
                $balance_ag = '0.00';
                */
                //$balance_mg = '0.00';
				$balance_sb = '0.00';
                //$balance_pt = '0.00';
                
                $this->assign('balance_bb',$balance_bb);
                $this->assign('balance_og',$balance_og);
                $this->assign('balance_ag',$balance_ag);
                $this->assign('balance_mg',$balance_mg);
                $this->assign('balance_sb',$balance_sb);
                $this->assign('balance_pt',$balance_pt);
            }
    	    return $this->fetch();
    	}else{

	        $zz_type=input("zz_type/d");
	        $zz_money=input("zz_money/d");
	        if($zz_money<1)
	        {
	            message("转账金额最低为：1元，请重新输入");
	        }
    	        
	        $t_type="d";
	        switch ($zz_type)
	        {
	            case 12:
	            case 111:
	            case 13:
	            case 14:
	            case 19:
	            case 17:
	            case 16:
	            case 77:
	                $type = "IN";
	                break;
	            case 22:
	            case 211:
	            case 23:
	            case 24:
	            case 29:
	            case 27:
	            case 26:
	            case 87:
	                $type = "OUT";
	                break;
	            default:
	                message("转账类型非法！");
	                break;
	        }
	        
	        if ($type=="IN" || $type=="OUT"){
                $moneyA = $user["money"];
                if ($type=="IN") {
                    if ($userinfo["money"] < $zz_money) {
                        message("体育/彩票额度不足！");
                    }
    	                    
                    if (14 == $zz_type) {
                        $type="NA";
                    } else if (12 == $zz_type) {
                        $type="AG";
                    } else if (18 == $zz_type) {
                        $type="NT";
                    } else if (111 == $zz_type) {
                        $type="BBIN";
                    } else if (13 == $zz_type) {
                        $type="MG";
                    }  else if (17 == $zz_type) {
                        $type="OG";
                    }else if (16 == $zz_type) {
                        $type="sbet";
                    }else if (77 == $zz_type) {
                        $type="PT";
                    }else {
                        message("类型错误！");
                    }
    	                    
                    // 检查 AG,MG,BB,NA总的余额， 转入的时候， 是要扣除总余额的
                    $targetTmp = strtoupper($type);
                    if ($targetTmp == 'BBIN') {
                        $targetTmp = 'BB';
                    }       
                    $web_zzzzz = db('web_zzzzz')->where('name',$targetTmp)->find();
                    $muqian = $web_zzzzz['muqian'];
                    if ($muqian < $zz_money) {
                        message("您申请转入的真人娱乐额度不足请联系客服！");
                    }
    	                    
                    $inStatus = 0;
                    //NA
                    if(14 == $zz_type){
                        /*
                        $userParms = array("userName"=>$temp_username,"amount"=>$zz_money);
                        $inStatus = NAUtil::na_palyer_trans_deposit($userParms);
                        $type="NA";
                        */
                        message("NA平台接口开发中！");
                    }
                    //AG
                    else if(12 == $zz_type){
                        $type="AG";
                        $billno = "HGA{$uid}AG".time().rand(10,99);
                        
                        $result = agGame::regUser($temp_username);
                        if(!$result['Code']){
                            $trans_result = agGame::depositToAG($temp_username,$zz_money,$billno);
                            if($trans_result == true){
                                $inStatus = 1;
                            }
                        }
                        
                        //$inStatus = 1;
                    }else if(17 == $zz_type){ //og
                        $type="OG";
                        $billNO = $billno ='jinpai'. rand(10,9999).date("mdHis");
                        if(strval(oggame::CheckAndCreateAccount($temp_username, 'oga123456'))){
                            $trans_result = oggame::TransferCredit($temp_username,"oga123456",$billno,'IN',$zz_money);
                            if($trans_result === '1'){
                                $inStatus = 1;
                            }elseif($trans_result == '2'){
                                oggame::ConfirmTransferCredit($temp_username,"oga123456",$billno,'IN',$zz_money);
                                $inStatus = 1;
                            }
                        }                        
                    }
                    //NT
                    else if(18 == $zz_type){
                        /*
                        $type="NT";
                        $inParms = array("userID"=>$temp_username,"amount"=>$zz_money,"transactionID"=>$billno);
                        $trans_result = NASlotUtil::accountCredit($inParms);
                        if($trans_result == 200){
                            $inStatus = 1;
                        }
                        */
                        message("NT平台接口开发中！");
                    }//BBIN
                    else if(16 == $zz_type){ //sbet
 
                        $type="sbet";
                        $billNO = $billno =$username."-addmoney-".time();
                        //if(time() - session('sunbetTokenTime') > 3600 || session('authtoken') == ""){
                        if( (time() - session('sunbetTokenTime')?:0) > 3600){                            
                            $token = sunbet::getToken();
                            if($token){
                                session('sunbetTokenTime',time());
                                session('sunbetToken',$token);
                                $authtoken = sunbet::authorize(session('sunbetToken'),$temp_username);
                                session('authtoken',$authtoken);
                            }else{
                                message('获取token失败');
                            }
                        }
                        $trans_result = sunbet::addMoney(session('sunbetToken'), $zz_money,$temp_username);
                        //{"bal":70.00,"cur":"RMB","txid":"test001jsa-addmoney-1502270331","ptxid":"a2275094-e37c-e711-80be-0050568c10c1","dup":false}"
                        $trans_result = json_decode($trans_result);
                        if($trans_result && !@$trans_result->err){
                            $billno = $trans_result->txid;
                            $inStatus = 1;
                        }
                    }else if(111 == $zz_type){
                        $billno = $uid.time();
                        bbingame::CreateMember($temp_username,$password);
                        $type="BBIN";
                        $trans_result = bbingame::Transfer($temp_username,$zz_money,$billno,$act = 'IN');        
                        if($trans_result === true){
                            $inStatus = 1;
                        }
                        
                    }//MG
                    else if(13 == $zz_type){
                        $type="MG";
                        //$trans_result = $mgapi->deposit($mg_username,$zz_money,$qGuid);
                        $mg_username =  $username.'@hga';
                        $billno ='mg'. rand(10,9999).date("mdHis");//订单号杜撰
                        $account_id = '';//'小罗说有固定生成规则'
                        //$account_ext_ref = 'lisi5787@hga';
                        $account_ext_ref = $mg_username;                        
                        //$auth = 'Basic R2FtaW5nTWFzdGVyMUNOWV9hdXRoOjlGSE9SUWRHVFp3cURYRkBeaVpeS1JNZ1U=';
                        $auth = mgGame::authenticate();
                        $auth = $auth['body']['access_token'];
                        $trans_result = mgGame::createTranscation($auth,$zz_money,$billno,0,$account_ext_ref, $account_id);
                        
                        if($trans_result['success'] == true){
                            $inStatus = 1;
                        }
                        /*
                        if($trans_result['Code'] == 6){
                            $mgguid = $mgapi->getGUID();
                            $qGuid =$mgguid['Data'];
                            $guids = array(time(),$qGuid);
                            $_SESSION["userGUID"]= $guids;
                        }    
                        */
                    }else if(77 == $zz_type){
                        //PT
                        $type="PT";
                        $billno ='pt'. rand(10,9999).date("mdHis");//订单号杜撰
                        $ret = ptGamePlayer::create($temp_username);
                        $ret = ptGamePlayer::deposit($temp_username,$zz_money,$billno);
                        if(isset($ret['result']) && isset($ret['result']['result']) && $ret['result']['result'] == 'Deposit OK'){
                            $inStatus = 1;
                        }else{
                            message($ret["errorcode"].$ret["error"]);
                            
                        } 
                    }
                    
                    Db::startTrans();

                    if(1 == $inStatus){    	                        
                        db('web_zzzzz')->where('name',$targetTmp)->setInc('xiaofei',$zz_money);
                        db('web_zzzzz')->where('name',$targetTmp)->setDec('muqian',$zz_money);
                        $status = 1;
                        $about = "转入".$targetTmp;
                        $order = date("YmdHis")."_".session('username');

                        $insert_data = [
                            'uid'   =>  $uid,
                            'm_value'   =>  $zz_money,
                            'status'    =>  $status,
                            'm_order'   =>  $order,
                            'pay_card'  =>  $userinfo["pay_card"],
                            'pay_num'   =>  $userinfo["pay_num"],
                            'pay_address'   =>  $userinfo["pay_address"],
                            'pay_name'  =>  $userinfo['pay_name'],
                            'about'     =>  $about,
                            'assets'    =>  $userinfo["money"],
                            'balance'     =>  $userinfo["money"]+$zz_money,
                            'type'      =>  input('zz_type'),
                        ];
                        db('k_money')->insert($insert_data);
                        
                        $new_money = $user['money'] - abs($zz_money);
                        $data = [
                            'uid'       =>  $uid,
                            'username'  =>  $username,
                            'old_money' =>  $user['money'],
                            'amount'    =>  $zz_money,
                            'new_money' =>  $new_money,
                            'type'      =>  $zz_type,
                            'info'      =>  '转入'.$targetTmp,
                            'actionTime'    =>  time(),
                            'result'    =>  '转账成功',
                            'billNO'    =>  $billno,
                            'status'    =>  1,
                        ];
                        db('zz_info')->insert($data);

                        db('k_user')->where('uid',$uid)->setDec('money',$zz_money);
                        
                        $moneyB = $moneyA-$zz_money; //转账后额度
                        //写入转账记录
                        $zr_username = $temp_username;
                        $zz_time = date("Y-m-d H:i:s");
                        
                        $data = [
                            'live_type' =>  $type,
                            'zz_type'   =>  $zz_type,
                            'uid'       =>  $uid,
                            'username'  =>  $username,
                            'zr_username'   =>  $zr_username,
                            'zz_money'  =>  $zz_money,
                            'ok'        =>  1,
                            'result'    =>  '转账成功',
                            'zz_num'    =>  0,
                            'zz_time'   =>  $zz_time,
                            'billno'    =>  $billno,
                            'moneyA'    =>  $moneyA,
                            'moneyB'    =>  $moneyB,
                        ];
                        //db('ag_zhenren_zz')->insert($data);
                        //往AG真人转账表中添加记录不合理,这里自行注释掉;
             
                        Db::commit();
                        message("转账成功,转账金额为".intval($zz_money));
                    }else{
                        Db::rollback();
                        message("转账失败，请联系客服");
                    }
    	                    
                }
    	                
                if($type=="OUT"){
                    $outStatus = 0;
                    //NA
                    if(24 == $zz_type){
                        /*
                        $userParms = array("userName"=>$temp_username,"amount"=>$zz_money);
                        $outStatus = NAUtil::na_palyer_trans_withdrawal($userParms);
                        $type="NA";
                        */
                        message('NA平台接口开发中！');
                    }
                    //AG
                    else if(22 == $zz_type){
                        $type="AG";
                        $billno = "HGA{$uid}AG".time().rand(10,99);
                        $result = agGame::regUser($temp_username);
                        if(!$result['Code']){
                            $trans_result = agGame::AGToWithdrawal($temp_username,$zz_money,$billno);
                            if($trans_result == '1'){
                                $outStatus = 1;
                            }
                        }
                    }else if(26 == $zz_type){
                        $type = "sbet";
                        //if(time() - session('sunbetTokenTime') > 3600 || session('authtoken') == ""){     
                        if( (time() - session('sunbetTokenTime')?:0) > 3600){
                            $token = sunbet::getToken();
                            if($token){
                                session('sunbetTokenTime',time());
                                session('sunbetToken',$token);
                                $authtoken = sunbet::authorize(session('sunbetToken'),$temp_username);
                                session('authtoken',$authtoken);
                            }else{
                                message('获取token失败');
                            }
                        }
                        $trans_result = sunbet::reduceMoney(session('sunbetToken'), $zz_money,$temp_username);
                        $trans_result = json_decode($trans_result);
                        if($trans_result && !@$trans_result->err){
                            $billno = $billNO = $trans_result->txid;
                            $outStatus = 1;
                        }
                    }else if(27 == $zz_type){ //og
                        /*
                        $type="OG";
                        $billNO = $billno ='jinpai'. rand(10,9999).date("mdHis");          
                        $trans_result = oggame::TransferCredit($temp_username,"oga123456",$billno,'OUT',$zz_money);
                        if($trans_result == '1'){
                            $outStatus = 1;
                        }
                        */
                        $type="OG";
                        $billNO = $billno ='jinpai'. rand(10,9999).date("mdHis");
                        if(strval(oggame::CheckAndCreateAccount($temp_username, 'oga123456'))){
                            $trans_result = oggame::TransferCredit($temp_username,"oga123456",$billno,'OUT',$zz_money);
                            if($trans_result === '1'){
                                $outStatus = 1;
                            }elseif($trans_result === '2'){
                                oggame::ConfirmTransferCredit($temp_username,"oga123456",$billno,'OUT',$zz_money);
                                $outStatus = 1;
                            }
                        } 
                    }//NT
                    else if(28 == $zz_type){
                        /*
                        $type="NT";
                        $outParms = array("userID"=>$temp_username,"amount"=>$zz_money,"transactionID"=>$billno);
                        $trans_result = NASlotUtil::accountDebit($outParms);
                        if($trans_result == 200){
                            $outStatus = 1;
                        }
                        */
                        message('NT平台接口开发中!');
                    }//BBIN
                    else if(211 == $zz_type){
                        bbinGame::CreateMember($temp_username,$password);
                        $type="BBIN";
                        $billno = '0'.$uid.time();
                        //$trans_result = $bbapi->withdrawal($temp_username,$zz_money,$billno);
                        $trans_result = bbingame::Transfer($temp_username,$zz_money,$billno,$act = 'OUT');
                        if($trans_result == true){
                            $outStatus = 1;
                        }
                        
                    } //MG
                    else if(23 == $zz_type){
                        $type="MG";
                        $billno ='mg'. rand(10,9999).date("mdHis");//订单号杜撰
                        $account_id = '';//'小罗说有固定生成规则'
                        //$account_ext_ref = 'lisi5787@hga';         
                        $mg_username =  $username.'@hga';
                        $account_ext_ref = $mg_username;  
                        //$auth = 'Basic R2FtaW5nTWFzdGVyMUNOWV9hdXRoOjlGSE9SUWRHVFp3cURYRkBeaVpeS1JNZ1U=';
                        $auth = mgGame::authenticate();
                        $auth = $auth['body']['access_token'];
                        $trans_result = mgGame::createTranscation($auth,$zz_money,$billno,1,$account_ext_ref, $account_id);
   
                        if($trans_result['success'] == true){
                            $outStatus = 1;
                        }   
    	            }else if(87 == $zz_type){
    	                //PT
    	                $type="PT";
    	                $billno ='pt'. rand(10,9999).date("mdHis");//订单号杜撰
    	                $ret = ptGamePlayer::create($temp_username);
    	                $ret = ptGamePlayer::withdraw($temp_username,$zz_money,$billno);
    	                if(isset($ret['result']) && isset($ret['result']['result']) && $ret['result']['result'] == 'Withdraw OK'){
    	                    $outStatus = 1;
    	                }else{
    	                    message($ret["errorcode"].$ret["error"]);
    	                    
    	                }      	                
    	            }
                    
    	            Db::startTrans();

                    // 检查 AG,MG,BB,NA总的余额， 转入的时候， 是要扣除总余额的
                    $targetTmp = strtoupper($type);
                    if ($targetTmp == 'BBIN') {
                        $targetTmp = 'BB';
                    }

                    if(1 == $outStatus){
                        
                        db('web_zzzzz')->where('name',$targetTmp)->setDec('xiaofei',$zz_money);
                        db('web_zzzzz')->where('name',$targetTmp)->setInc('muqian',$zz_money);
                                                
                        $status = 1;
                        $about = $targetTmp."转出";
                        $order = date("YmdHis")."_".$user['username'];

                        $insert_data = [
                            'uid'   =>  $uid,
                            'm_value'   =>  $zz_money,
                            'status'    =>  $status,
                            'm_order'   =>  $order,
                            'pay_card'  =>  $userinfo["pay_card"],
                            'pay_num'   =>  $userinfo["pay_num"],
                            'pay_address'   =>  $userinfo["pay_address"],
                            'pay_name'  =>  $userinfo['pay_name'],
                            'about'     =>  $about,
                            'assets'    =>  $userinfo["money"],
                            'balance'     =>  $userinfo["money"]+$zz_money,
                            'type'      =>  input('zz_type'),
                        ];
                        db('k_money')->insert($insert_data);
    	                        
                        $new_money = $user['money'] + $zz_money;
                        $data = [
                            'uid'       =>  $uid,
                            'username'  =>  $username,
                            'old_money' =>  $user['money'],
                            'amount'    =>  $zz_money,
                            'new_money' =>  $new_money,
                            'type'      =>  $zz_type,
                            'info'      =>  $targetTmp.'转出',
                            'actionTime'    =>  time(),
                            'result'    =>  '转账成功',
                            'billNO'    =>  $billno,
                            'status'    =>  1,
                        ];
                        db('zz_info')->insert($data);
                        
                        db('k_user')->where('uid',$uid)->setInc('money',$zz_money);
                        
                        $moneyB = $moneyA+$zz_money; //转账后额度
                        //写入转账记录
                        
                        $zr_username = $temp_username;
                        $zz_time = date("Y-m-d H:i:s");
                        
                        $data = [
                            'live_type' =>  'AG',
                            'zz_type'   =>  $zz_type,
                            'uid'       =>  $uid,
                            'username'  =>  $username,
                            'zr_username'   =>  $zr_username,
                            'zz_money'  =>  $zz_money,
                            'ok'        =>  1,
                            'result'    =>  '转账成功',
                            'zz_num'    =>  0,
                            'zz_time'   =>  $zz_time,
                            'billno'    =>  $billno,
                            'moneyA'    =>  $moneyA,
                            'moneyB'    =>  $moneyB,
                        ];
                        //db('ag_zhenren_zz')->insert($data);

                        Db::commit();
                        message("转账成功,转账金额为".intval($zz_money));
                    }else{
                        Db::rollback();
                        message("真人点数不足，转账失败！");
                    }
                }
            } else {
                message("转账类型非法！");
                exit();
            }
        }
    }
    
    public function balance($type){//ajax余额查询
        $uid = session('uid');
        $user = db('k_user')->where('uid',$uid)->find();
        $userinfo = $user;
        
        //用户名
        $username  = session('username');
        $password = substr(md5(md5($username)),3,12);
        
        if (mb_substr($username,-3,3,'utf-8') != 'hga') {
            $temp_username = $username . 'hga';
        } else {
            $temp_username = $username;
        }
        $mg_username = $username.'@hga';
        
        //$zrtype = input('type');
        $zrtype = $type;
        switch ($zrtype){
            case 'bbzr':
            	bbingame::CreateMember($temp_username,$password);
                $bbRet = bbingame::CheckUsrBalance($temp_username);
                //dump($bbRet);return $bbRet;
                if($bbRet === false){
                    return ['status' => 1,"msg"=>'未知，请联系管理员！'];
                }else{
                    $bb_balance = $bbRet;
                    return ['status' => 0, 'money'=>sprintf("%.2f",$bb_balance),'type' =>'bbzr'];
                }                
            case 'ogzr':
            	oggame::CheckAndCreateAccount($temp_username, 'oga123456');
                //查询OG金额
                if ($temp_username != '') {
                    $og_balance = oggame::GetBalance($temp_username,'oga123456'); //og::getUserInfo($user['og_username']);
                } else {
                    $og_balance ='0.00';
                }
                return ['status'=>0, 'money'=>sprintf("%.2f", $og_balance),'type'=>'ogzr'];
            case 'agzr':
            	$result = agGame::regUser($temp_username);
                //查询ag金额
                $ag_balance =agGame::inquireBalance($temp_username);
                //dump($ag_balance);return $ag_balance;                                
                return ['status' => 0,'money'=>sprintf("%.2f", $ag_balance),'type'=>'agzr'];
            case 'na':
                return ['money'=>sprintf("%.2f", '0.00'),'type'=>'nazr'];
                /*
                $userParms = array("userName"=>$temp_username);
                $na_balance = $isWeihu?'维护中': NAUtil::na_palyer_balance($userParms);
                if(''==$na_balance){
                    if($isWeihu){
                        $na_balance = "维护中";
                    }else{
                        $sql		=	"SELECT password as s FROM `k_user` where uid=$uid ";
                        $query		=	$mysqli->query($sql);
                        $rs			=	$query->fetch_array();
                        $userPwd	=	$rs['s'];
                        $userPwd = substr(md5($temp_username),16);
                        NAUtil::create_na_user(array("userName"=>$temp_username,"userPwd"=>$userPwd,"userType"=>"1"));
                        $na_balance = NAUtil::na_palyer_balance($userParms);
                    }
                    
                }
                echo json_encode(array('money'=>sprintf("%.2f", $na_balance),'type'=>'nazr'));
                break;
                */
            case 'mgzr':
                //$mgRet = $mgapi->balance($mg_username,$qGuid);
                $auth = mgGame::authenticate();
                $auth = $auth['body']['access_token'];
                //$account_ext_ref = 'lisi5787@hga';
                $account_ext_ref = $mg_username;
            	mgGame::createMember($auth,$mg_username,$password,$account_ext_ref);
                $mgRet = mgGame::getBalance($auth,$account_ext_ref);
                //dump($mgRet);return $mgRet;
                if($mgRet['success'] == false){
                    $msg = $mgRet['body']['code'] . $mgRet['body']['message'];
                    return ['status' => 1,'msg' => $msg];
                }else{
                    $mg_balance = $mgRet['body'][0]['credit_balance'];
                }
                return ['status' => 0 ,'money'=> sprintf("%.2f",$mg_balance),'type'=>'mgzr'];
            case 'sbzr':
                //if(time() - session('sunbetTokenTime') > 3600){
                if( (time() - session('sunbetTokenTime')?:0) > 3600){
                    $token = sunbet::getToken();
                    if($token){
                        session('sunbetTokenTime',time());
                        session('sunbetToken',$token);
                        $authtoken = sunbet::authorize(session('sunbetToken'),$temp_username);
                        session('authtoken',$authtoken);
                    }else{
                        return ['status'=>1,'msg'=>'获取token失败'];
                    }
                }
                if(session('?sunbetToken')){
                  	sunbet::create($temp_username,$password);
                    $sb_balance = sunbet::getBalance(session('sunbetToken'),$temp_username);
                    $sb_balance = $sb_balance->bal;
                }else{
                    $sb_balance = '0.00';
                }
                return ['status'=>0, 'money'=> sprintf("%.2f",$sb_balance),'type'=>'sbzr'];
            case 'ptzr':
                $ret = ptGamePlayer::create($temp_username);
                $ret = ptGamePlayer::balance($temp_username);
                if(@$ret['error']){
                    return ['status' => 1,"msg"=>$ret["errorcode"].$ret["error"],"money"=>0,];
                }else{
                    $pt_balance = $ret['result']['balance'];
                    return ['status' => 0, 'money'=>sprintf("%.2f",$pt_balance),'type' =>'ptzr'];
                }     
        }
    }


    /**
     *   获取提现记录接口
     *
     *  充值和提现状态，0代表失败，1代表成功，2代表确认'
     */
    public function cashRecord()
    {
        try{
            //定义数据返回格式
            $response['msg']    = '' ; //返回信息
            $response['status'] = self::STATUS_ERROR  ; //请求状态
            $response['data']   = [] ; //数据
            $response['page']   = [] ; //分页数据
            $response['amount'] = [] ; //汇款成功总金额

            //当前多少页
            $page = intval($this->request->param('page')) ;
            $page = ($page) ? $page : 1  ;
            //每页多少条
            $perPage = intval($this->request->param('per')) ;
            $perPage = ($perPage) ? $perPage : 20  ;

            //获取提现记录数据
            $info = $this->getCashRecordData($page,$perPage);
            $data = json_decode(json_encode($info['data']),true) ;

            //组合返回数据
            $response['msg']    = 'success' ;
            $response['status'] = self::STATUS_SUCCESS ;
            $response['data']   =  $data;
            $response['page']   = $this->getRecordPageData($info) ; //组合分页返回数据
            $response['amount'] = $this->getCashSuccessAmount($data) ; //得到提现成功总金额

        } catch (\Exception $e) {
            $response['status'] =  self::STATUS_ERROR ;
            if ($e->getMessage() == 10001) {
                $response['msg'] = '系统中没有查询到该用户...' ;
            } else {
                $response['msg'] = '网络延迟,请联系技术人员...' ;
            }
        }
        return $response ;
    }
    //获取提现记录数据
    private  function getCashRecordData($page,$perPage)
    {
        try {
            $uid = Session::get('uid');
            $user = Db::table('k_user')->where(array('uid'=>$uid))->find();
            if (empty($user)) {
                throw new \Exception(10001) ;
            }
            $where = ' uid = "'.$user['uid'].'" and m_value < 0 ';
            $data  = Db::table('k_money')->field('m_make_time,m_value,m_order,status')->where($where)->order('m_id desc')->paginate($perPage,false,['page'=>$page])->toArray();

            return  $data ;
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage()) ;
        }
    }
    //得到提现成功总金额
    private  function getCashSuccessAmount(&$data)
    {
        try {
            $amount =0 ;
            if (!empty($data)) {
                foreach ($data  as $val) {
                    if ($val['status'] == 1) {
                        $amount = bcadd($amount,$val['m_value'],2) ;
                    }
                }
            }
            return $amount ;
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage()) ;
        }
    }


    /**
     *  获取存款记录接口
     *
     *  数据库记录状态值(stattus)说明   1:成功 0:失败 其他:系统审核中
     *
     *  返回数据类型格式
     * $response['msg'] = '' ; //请求信息
     * $response['status']  = 0  ; // 0 成功 1 失败
     * $response['data']    = [] ; //返回数据
     */
    public function rechargeRecord()
    {
        //定义数据返回格式
        $response['msg']    = '' ; //返回信息
        $response['status'] = self::STATUS_ERROR  ; //请求状态
        $response['data']   = [] ; //数据
        $response['page']   = [] ; //分页数据
        $response['amount'] = [] ; //总存款金额

       try{
           //当前多少页
           $page = intval($this->request->param('page')) ;
           $page = ($page) ? $page : 1  ;
           //每页多少条
           $perPage = intval($this->request->param('per')) ;
           $perPage = ($perPage) ? $perPage : 20  ;

           //用户数据处理
           $uid  = Session::get('uid');
           $user = Db::table('k_user')->where(array('uid'=>$uid))->find();
           if (empty($user)) {
               throw new \Exception(10001) ;
           }

           //查询数据
           $where = ' uid = "'.$user['uid'].'" and m_value > 0 and (type = 1 or type = 100)';
           $info = Db::table('k_money')->where($where)->field('m_make_time,m_value,status')->order('m_id desc')->paginate($perPage,false,['page'=>$page])->toArray();
           $data = json_decode(json_encode($info['data']),true);

           $response['msg']    = 'success';
           $response['status'] = self::STATUS_SUCCESS ;
           $response['page']   = $this->getRecordPageData($info) ; //组合分页返回数据
           $response['data']   = $this->formatRechargeRecordData($data) ; //格式化存款记录数据
           $response['amount'] = $this->getCountRechargeRecordData($data) ; //得到存款成功总金额

       } catch (\Exception $e) {
           $response['status'] = self::STATUS_ERROR ;
           if ($e->getMessage() == 10001) {
               $response['msg'] = '找不到该用户数据';
           } elseif ($e->getMessage() == 10002) {
               $response['msg'] = '没有对应的数据';
           } else {
               $response['msg'] = '网络延迟,请联系技术人员';
           }
       }
       return $response ;
    }
    //获取存款记录总价格
    private  function  getCountRechargeRecordData(&$data)
    {
        $amount = 0 ;
        try{
            if (!empty($data)) {
                foreach($data as $record) {
                    if ($record['status'] == 1) {
                        $amount = bcadd($amount,$record['m_value'],2) ;
                    }
                }
            }
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage()) ;
        }
        return $amount ;
    }
    //格式化存款记录数据
    private  function formatRechargeRecordData($data)
    {
        try{
            if (!empty($data)) {
                foreach ($data as $key=>$val) {
                    $data[$key]['type'] = 1 ; //1为在线充值
                }
            }
        } catch (\Exception $e){
            throw new \Exception($e->getMessage()) ;
        }
        return $data ;
    }


    /**
     *  获取汇款记录数据接口
     *
     *  汇款记录说明  0:失败 1:成功 2:系统审核中
     */
    public function remittanceRecord()
    {
        try{
            //定义数据返回格式
            $response['msg']    = '' ; //返回信息
            $response['status'] = self::STATUS_ERROR  ; //请求状态
            $response['data']   = [] ; //数据
            $response['page']   = [] ; //分页数据
            $response['amount'] = [] ; //汇款成功总金额

            //当前多少页
            $page = intval($this->request->param('page')) ;
            $page = ($page) ? $page : 1  ;
            //每页多少条
            $perPage = intval($this->request->param('per')) ;
            $perPage = ($perPage) ? $perPage : 20  ;

            //获取提现记录数据
            $info = $this->getRemittanceRecordData($page,$perPage);
            $data = json_decode(json_encode($info['data']),true) ;

            //组合返回数据
            $response['msg']    = 'success' ;
            $response['status'] = self::STATUS_SUCCESS ;
            $response['data']   = $this->formatRemittanceData($data); //格式化汇款数据
            $response['page']   = $this->getRecordPageData($info) ; //组合分页返回数据
            $response['amount'] = $this->getRemittanceSuccessAmount($data) ; //得到汇款成功总金额

        } catch (\Exception $e) {
            $response['status']  =  self::STATUS_ERROR ;
            if ($e->getMessage() == 10001) {
                $response['msg'] = '系统中没有查询到该用户...' ;
            } else {
                $response['msg'] = '网络延迟,请联系技术人员...' ;
            }
        }

        return $response ;
    }
    //获取汇款记录数据
    private  function getRemittanceRecordData($page,$perPage)
    {
        try {
            $uid = Session::get('uid');
            $user = Db::table('k_user')->where(array('uid'=>$uid))->find();
            if (empty($user)) { throw new \Exception(10001) ; }

            $data  = Db::table('huikuan')->field('lsh,date,money,zsjr,bank,manner,status')->where(array('uid'=>$uid))->order('id desc')->paginate($perPage,false,['page'=>$page])->toArray();

            return  $data ;
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage()) ;
        }
    }
    //得到汇款成功总金额
    private  function getRemittanceSuccessAmount(&$data)
    {
        try {
            $amount =0 ;
            if (!empty($data)) {
                foreach ($data  as $val) {
                    if ($val['status'] == 1) {
                        $amount = bcadd($amount,$val['money'],2) ;
                    }
                }
            }
            return $amount ;
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage()) ;
        }
    }
    //格式化汇款数据
    private  function  formatRemittanceData($data)
    {
        try {
            if (!empty($data)) {
                foreach ($data as $key=>$val) {
                   $data[$key]['score'] = 0 ;
                }
            }
            return $data ;
         }catch ( \Exception $e) {
            throw new \Exception($e->getMessage()) ;
        }
    }

    /**
     * 组合分页数据
     * @param $info
     * @return mixed
     */
    private function getRecordPageData(&$info)
    {
        $res['total']         = isset($info['total'])  ? $info['total'] : 0 ;
        $res['current_page']  = isset($info['current_page'])  ? $info['current_page'] : 0 ;
        $res['per_page']      = isset($info['per_page'])  ? $info['per_page'] : 0 ;
        $res['last_page']     = isset($info['last_page'])  ? $info['last_page'] : 0 ;
        return $res ;
    }


    
    public function zr_data_money(){  //转换记录
    	$uid = Session::get('uid');
    	$user = Db::table('k_user')->where(array('uid'=>$uid))->find();
    	
    	$num = Db::table('zz_info')->where(array('uid'=>$uid))->count();//总数
    	$zzinfo = Db::table('zz_info')->where(array('uid'=>$uid))->order('id desc')->paginate(2,$num);
    	$page = $zzinfo->render();
    	
    	$this->assign('zzinfo',$zzinfo);
    	$this->assign('user',$user);
    	$this->assign('page',$page);
    	return $this->fetch('zr_data_money');
    }
    
    public function password(){ //资金&登录 密码修改
    	$uid = Session::get('uid');
    	$user = Db::table('k_user')->where(array('uid'=>$uid))->find();
    	if($_POST){
    		$type = $_POST['formtype'];
    		if($type=='login'){
    			$oldpass = trim($_POST['oldpass']);
    			$userinfo = Db::table('k_user')->where(array('uid'=>$user['uid'],'password'=>md5($oldpass)))->find();
    			if(!$userinfo){
    				message("原始登录密码不正确！");
    			}
    			$newpass = trim($_POST['newpass']);
    			$newpass2 = trim($_POST['newpass2']);
    			if($newpass!=$newpass2){
    				message("两次密码不一致！");
    			}
    			$users = Db::table('k_user')->where(array('uid'=>$user['uid'],'password'=>md5($newpass)))->find();
    			if($users){
    				message("新密码不能与近期密码相同！");
    			}
    			$data['password'] = md5($newpass);
    			$res = Db::table('k_user')->where(array('uid'=>$user['uid']))->update($data);
    			if(!$res){
    				message("登录密码修改失败！");
    			}else {
    				unset($_SESSION);
    				session_destroy();
    				message("登录密码修改成功");
    			}
    		}else{
    			$oldmoneypass = trim($_POST['oldmoneypass']);
    			$userinfo = Db::table('k_user')->where(array('uid'=>$user['uid'],'qk_pwd'=>md5($oldmoneypass)))->find();
    			if(!$userinfo){
    				message("原始资金密码不正确！");
    			}
    			$newmoneypass = trim($_POST['newmoneypass']);
    			$newmoneypass2 = trim($_POST['newmoneypass2']);
    			if($newmoneypass!=$newmoneypass2){
    				message("两次资金密码不一致！");
    			}
    			$users = Db::table('k_user')->where(array('uid'=>$user['uid'],'qk_pwd'=>md5($newmoneypass)))->find();
    			if($users){
    				message("新资金密码不能与近期支付密码相同！");
    			}
    			$data['qk_pwd'] = md5($newmoneypass);
    			$res = Db::table('k_user')->where(array('uid'=>$user['uid']))->update($data);
    			if(!$res){
    				message("资金密码修改失败！");
    			}else {
    				message("资金密码修改成功");
    			}	
    		}
    	}
    	$this->assign('user',$user);
    	return $this->fetch('password');
    }

    //游戏记录 体育单式
    public function record_ds(){
    	$uid = Session::get('uid');
    	$user = Db::table('k_user')->where(array('uid'=>$uid))->find();
    	$list = Db::table('k_bet')->where(array('uid'=>$user['uid']))->order('bet_time desc')->paginate(15);
    	$this->assign('list',$list);
    	$this->assign('ky',0);
    	$this->assign('bet_money',0);
    	$this->assign('bgcolor','');
    	$this->assign('user',$user);
    	$this->assign('score',NULL);
    	return $this->fetch('record_ds');
    }

    /**
     *  未结注单 体育单式 数据接口
     *
     * @return mixed
     */
    public function sportDs()
    {
        try{
            //定义数据返回格式
            $response['msg']            = '' ; //返回信息
            $response['status']         = 0  ; //请求状态
            $response['data']   = [] ; //数据
            $response['page']   = [] ; //分页数据

            //当前多少页
            $page = intval($this->request->param('page')) ;
            $page = ($page) ? $page : 1  ;
            //每页多少条
            $perPage = intval($this->request->param('per')) ;
            $perPage = ($perPage) ? $perPage : 20  ;

            //获取体育单式数据
            $info = $this->getSportDsData($page,$perPage);
            $data = json_decode(json_encode($info['data']),true) ;

            //组合返回数据
            $response['data']   = $this->formatSportDsData($data); //格式化数据
            $response['page']   = $this->getRecordPageData($info) ; //组合分页返回数据
            $response['msg']    = 'success' ;
            $response['status'] = 1 ;

        } catch (\Exception $e) {
            $response['status']  =  0 ;
            if ($e->getMessage() == 10001) {
                $response['msg'] = '系统中没有查询到该用户...' ;
            } else {
                $response['msg'] = '网络延迟,请联系技术人员...' ;
            }
        }

        return $response ;
    }
    //获取体育单式数据
    private  function getSportDsData($page,$perPage)
    {
        try {
            $uid = Session::get('uid');
            $user = Db::table('k_user')->where(array('uid'=>$uid))->find();
            if (empty($user)) {
                throw new \Exception(10001) ;
            }
            $data = Db::table('k_bet')->where(array('uid'=>$user['uid']))->order('bet_time desc')->paginate($perPage,false,['page'=>$page])->toArray();
            return $data ;
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage()) ;
        }
    }
    //格式化体育单式数据
    private function formatSportDsData($data)
    {
        $result = [];
        try {
            if ( !empty($data) ) {
                foreach ($data as $key=>$val) {

                    //第一列
                    $result[$key]['time']         = $val['bet_time']; //下注时间
                    $result[$key]['ball_sort']    =  $val['ball_sort']; //球类种类
                    $result[$key]['point_column'] = $this->disposePointColumn($val['point_column']); //下注赔率字段
                    $result[$key]['number']       = 'HG_'.$val['number'] ;

                    //投注详细信息列
                    $result[$key]['match_name'] = $val['match_name'] ; //联赛名
                    $result[$key]['match_type'] = $val['match_type'] ; //下注球赛类型
                    $result[$key]['score']      = $this->disposeScore($val)   ; //比分信息
                    $result[$key]['score_2']    = $this->disposeScore_2($val) ;//比分信息2

                    //第三列
                }
            }

       } catch(\Exception $e) {
            throw new \Exception($e->getMessage());
       }
        return $result ;
    }
    //体育单式比分信息获取
    private  function  disposeScore(&$data)
    {
        $result = '' ;
        try {
            if  ( ($data['point_column']=='match_ho') || ($data['point_column']=='match_ao')  || ($data['point_column']=='match_bao') ) {

                    if (  ($data['match_showtype']=='c') || ($data['match_showtype']=='C') ) {
                        $result .= $data['guest'].' '.$data['match_rgg'].' '.$data['master'].'(主)' ;
                    } else {
                        $result .= $data['master'].' '.$data['match_rgg'].' '.$data['guest'];
                    }

            } else {
                $result  .= $data['master'] ;
                switch ($data['point_column']) {

                    case 'match_bd10' :
                         $result .= '1:0 ' ;break ;
                    case 'match_bd20' :
                        $result .= '2:0 ' ;break ;
                    case 'match_bd21' :
                        $result .= '2:1 ' ;break ;
                    case 'match_bd30' :
                        $result .= '3:0 ' ;break ;
                    case 'match_bd31' :
                        $result .= '3:1 ' ;break ;
                    case 'match_bd32' :
                        $result .= '3:2 ' ;break ;
                    case 'match_bd40' :
                        $result .= '4:0 ' ;break ;
                    case 'match_bd41' :
                        $result .= '4:1 ' ;break ;
                    case 'match_bd42' :
                        $result .= '4:2 ' ;break ;
                    case 'match_bd43' :
                        $result .= '4:3 ' ;break ;

                    case 'match_bdg10' :
                        $result .= '1:0 ' ;break ;
                    case 'match_bdg20' :
                        $result .= '2:0 ' ;break ;
                    case 'match_bdg21' :
                        $result .= '2:1 ' ;break ;
                    case 'match_bdg30' :
                        $result .= '3:0 ' ;break ;
                    case 'match_bdg31' :
                        $result .= '3:1 ' ;break ;
                    case 'match_bdg32' :
                        $result .= '3:2 ' ;break ;
                    case 'match_bdg40' :
                        $result .= '4:0 ' ;break ;
                    case 'match_bdg41' :
                        $result .= '4:1 ' ;break ;
                    case 'match_bdg42' :
                        $result .= '4:2 ' ;break ;
                    case 'match_bdg43' :
                        $result .= '4:3 ' ;break ;

                    case 'match_bd00' :
                        $result .= '0:0 ' ;break ;
                    case 'match_bd11' :
                        $result .= '1:1 ' ;break ;
                    case 'match_bd22' :
                        $result .= '2:2 ' ;break ;
                    case 'match_bd33' :
                        $result .= '3:3 ' ;break ;
                    case 'match_bd44' :
                        $result .= '4:4 ' ;break ;

                    case 'match_bdup5' :
                        $result .= 'UP5 ' ;break ;
                    default:
                        $result .= ($data['guest']=='') ? ' VS. ' : '' ;
                }
            }

        } catch (\Exception $e) {
            throw new \Exception( $e->getMessage()) ;
        }

        return $result ;
    }
    //获取比分信息2
    private function disposeScore_2(&$data)
    {
        $result = '' ;
        //波胆
        $bd = ['match_hr_bd10','match_hr_bd20','match_hr_bd21','match_hr_bd30','match_hr_bd31','match_hr_bd32','match_hr_bd40','match_hr_bd41','match_hr_bd42','match_hr_bd43',
        'match_hr_bdg10','match_hr_bdg20','match_hr_bdg21','match_hr_bdg30','match_hr_bdg31','match_hr_bdg32','match_hr_bdg40','match_hr_bdg41','match_hr_bdg42','match_hr_bdg43',
        'match_hr_bd00','match_hr_bd11','match_hr_bd22','match_hr_bd33','match_hr_bd44','match_hr_bdup5',
        'match_bd10','match_bd20','match_bd21','match_bd30','match_bd31','match_bd32','match_bd40','match_bd41','match_bd42','match_bd43',
        'match_bdg10','match_bdg20','match_bdg21','match_bdg30','match_bdg31','match_bdg32','match_bdg40','match_bdg41','match_bdg42','match_bdg43',
        'match_bd00','match_bd11','match_bd22','match_bd33','match_bd44','match_bdup5',
        ] ;

        if ( ($data['ball_sort']=='冠军') || ($data['ball_sort']=='金融') ) {
                $ss = explode('@',$data['bet_info']) ;
                $result .= $ss[0].'@'.@$ss[1];
        } else {
                switch ($data['point_column']) {

                    case (in_array($data['point_column'],$bd)) :
                        $result .= '波胆  @ '.$data['bet_point'] ; break ;

                    case ($data['point_column'] =='match_ho' || $data['point_column']=='match_bho') :
                        $result .= $data['master'] . (!empty($data['match_nowscore']) ? $data['match_nowscore'] : '').' @ '.$data['bet_point'] ; break ;

                    case ($data['point_column'] =='match_ao' || $data['point_column']=='match_bao') :
                        $result .= $data['guest'] . (!empty($data['match_nowscore']) ? $data['match_nowscore'] : '').' @ '.$data['bet_point'] ; break ;

                    //标准盘
                    case ($data['point_column'] =='match_bzm' || $data['point_column']=='match_bmdy') :
                        $result .= $data['master'] .'独赢'.(!empty($data['match_nowscore']) ? $data['match_nowscore'] : '').' @ '.$data['bet_point'] ; break ;

                    case ($data['point_column'] =='match_bzg' || $data['point_column']=='match_bgdy') :
                        $result .= $data['guest'] .'独赢'.(!empty($data['match_nowscore']) ? $data['match_nowscore'] : '').' @ '.$data['bet_point'] ; break ;

                    //和局
                    case ($data['point_column'] =='match_bzh' || $data['point_column']=='match_bhdy') :
                        $result .=  '和局'.(!empty($data['match_nowscore']) ? $data['match_nowscore'] : '').' @ '.$data['bet_point'] ; break ;

                    case ($data['point_column'] =='match_bdpl' || $data['point_column']=='match_dxdpl') :
                        $result .=  'O'.$data['match_dxgg'].' @  '.$data['bet_point'] ; break ;

                    case ($data['point_column'] =='match_bxpl' || $data['point_column']=='match_dxxpl') :
                        $result .=  'U'.$data['match_dxgg'].' @  '.$data['bet_point'] ; break ;

                    case ($data['point_column'] =='match_dsdpl') :
                        $result .=  '单'.$data['match_dxgg'].' @  '.$data['bet_point'] ; break ;

                    case ($data['point_column'] =='match_dsdpl') :
                        $result .=  '双'.$data['match_dxgg'].' @  '.$data['bet_point'] ;  break ;

                    case ($data['point_column'] =='match_dsdpl') :
                        $result .=  '0~1  @ '.$data['bet_point'] ; break ;

                    case ($data['point_column'] =='match_total01pl') :
                        $result .=  '0~1  @'.$data['bet_point'] ; break ;

                    case ($data['point_column'] =='match_total23pl') :
                        $result .=  '2~3  @'.$data['bet_point'] ; break ;

                    case ($data['point_column'] =='match_total46pl') :
                        $result .=  '4~6  @'.$data['bet_point'] ; break ;

                    case ($data['point_column'] =='match_total7uppl') :
                        $result .=  '7UP  @'.$data['bet_point'] ; break ;

                    case ($data['point_column'] =='match_bqmm') :
                        $result .=   $data['master'].'/'.$data['master'].'@'.$data['bet_point'] ; break ;

                    case ($data['point_column'] =='match_bqmh') :
                        $result .=   $data['master'].'/和局'.'@'.$data['bet_point'] ; break ;

                    case ($data['point_column'] =='match_bqmg') :
                        $result .=   $data['master'].'/'.$data['guest'].'@'.$data['bet_point'] ; break ;

                    case ($data['point_column'] =='match_bqhm') :
                        $result .=  '和局'.'/'.$data['master'].'@'.$data['bet_point'] ; break ;

                    case ($data['point_column'] =='match_bqhm') :
                        $result .=  '和局'.'/'.'和局'.'@'.$data['bet_point'] ; break ;

                    case ($data['point_column'] =='match_bqhg') :
                        $result .=  '和局'.'/'.$data['guest'].'@'.$data['bet_point'] ; break ;

                    case ($data['point_column'] =='match_bqgm') :
                        $result .=  $data['guest'].'/'.$data['master'].'@'.$data['bet_point'] ; break ;

                    case ($data['point_column'] =='match_bqgh') :
                        $result .=  $data['guest'].'/'.'和局'.'@'.$data['bet_point'] ; break ;

                    case ($data['point_column'] =='match_bqgg') :
                        $result .=  $data['guest'].'/'.$data['guest'].'@'.$data['bet_point'] ; break ;

                }
        }
        return $result ;
    }
    
    //下注赔率字段处理
    private  function  disposePointColumn($data)
    {
        $str = '' ;
        //上半波胆主
        $sbbdz  = ['match_hr_bd10','match_hr_bd20','match_hr_bd21','match_hr_bd30','match_hr_bd31','match_hr_bd32','match_hr_bd40','match_hr_bd41','match_hr_bd42','match_hr_bd43'] ;
        //上半波胆客
        $sbbdk  = ['match_hr_bdg10','match_hr_bdg20','match_hr_bdg21','match_hr_bdg30','match_hr_bdg31','match_hr_bdg32','match_hr_bdg40','match_hr_bdg41','match_hr_bdg42','match_hr_bdg43'] ;
        //上半波胆平
        $sbbdp = ['match_hr_bd00','match_hr_bd11','match_hr_bd22','match_hr_bd33','match_hr_bd44','match_hr_bdup5'] ;
        //波胆主
        $bdz = ['match_bd10','match_bd20','match_bd21','match_bd30','match_bd31','match_bd32','match_bd40','match_bd41','match_bd42','match_bd43'] ;
        //波胆客
        $bdk = ['match_bdg10','match_bdg20','match_bdg21','match_bdg30','match_bdg31','match_bdg32','match_bdg40','match_bdg41','match_bdg42','match_bdg43'];
        //波胆平
        $bdp = ['match_bd00','match_bd11','match_bd22','match_bd33','match_bd44','match_bdup5'] ;
        //如球数
        $rqs = ['match_total01pl','match_total23pl','match_total46pl','match_total7uppl'] ;
        //半全场
        $bqc = ['match_bqmm','match_bqmh','match_bqmg','match_bqhm','match_bqhh','match_bqhg','match_bqgm','match_bqgh','match_bqgg'] ;

        switch ($data) {
            case ($data=='match_ho' || $data =='match_ao' ) :
                $str = '让球' ; break ;

            case ($data=='match_dxdpl' || $data =='match_dxxpl' ) :
                $str = '大小' ; break ;

            case ($data=='match_dsdpl' || $data =='match_dsspl' ) :
                $str = '单双' ; break ;

            case ($data=='match_bho' || $data =='match_bao' ) :
                $str = '上半场让球' ; break ;

            case ($data=='match_bdpl' || $data =='match_bxpl' ) :
                $str = '上半场大小' ; break ;

            case ($data=='match_bzm' || $data =='match_bzg' || $data =='match_bzh' ) :
                $str = '全场独赢' ; break ;

            case ($data=='match_bmdy' || $data =='match_bgdy' || $data =='match_bhdy' ) :
                $str = '半场独赢' ; break ;

            case (in_array($data,$sbbdz)) :
                $str = '上半波胆主' ; break ;

            case (in_array($data,$sbbdk)) :
                $str = '上半波胆客' ; break ;

            case (in_array($data,$sbbdp)) :
                $str = '上半波胆平' ; break ;

            case (in_array($data,$bdz)) :
                $str = '波胆主' ; break ;

            case (in_array($data,$bdk)) :
                $str = '波胆客' ; break ;

            case (in_array($data,$bdp)) :
                $str = '波胆平' ; break ;

            case (in_array($data,$rqs)) :
                $str = '如球数' ; break ;

            case (in_array($data,$bqc)) :
                $str = '半全场' ; break ;
        }

        return $str ;
    }
    
    public function record_cg(){ //游戏记录  体育串式
    	$uid = Session::get('uid');
    	$user = Db::table('k_user')->where(array('uid'=>$uid))->find();
    	//$betcg = Db::table('k_bet_cg')->where(array('uid'=>$user['uid'],'status'=>0))->order('bet_time desc')->select();
    	$betcg = Db::table('cglist') -> where('uid','=',$user['uid']) -> where('status','in',[0,2])->select();
    	$this->assign('betcg',$betcg);
    	$this->assign('ky',0);
    	$this->assign('bet_money',0);
    	$this->assign('bgcolor','');
    	$this->assign('current','');
    	$this->assign('canwin','');
    	$this->assign('user',$user);
    	$this->assign('line_count','0');
    	return $this->fetch('record_cg');
    }



    /**
     *  接口--已结注单详情
     */
    public  function cpAlreadyDetail()
    {
        try{
            //定义数据返回格式
            $response['msg']     = '' ; //返回信息
            $response['status']  = 0  ; //请求状态
            $response['data']    = [] ; //数据
            $response['page']    = [] ; //分页数据

            //当前多少页
            $page = intval($this->request->param('page')) ;
            $page = ($page) ? $page : 1  ;
            //每页多少条
            $perPage = intval($this->request->param('per')) ;
            $perPage = ($perPage) ? $perPage : 20  ;
            //查询日期
            $date =  $this->request->param('date') ;
            $date =  !empty($date) ? $date : date('Y-m-d',time()) ;

            //获取彩票未结注单数据
            $info = $this->getAlreadyDetailData($page,$perPage,$date) ;
            $data = json_decode(json_encode($info['data']),true) ;

            //组合返回数据
            $response['data']   = $this->formatCpDetailData($data)  ; //格式化数据
            $response['page']   = $this->getRecordPageData($info)   ; //组合分页返回数据
            $response['count']  = $this->getCpDetailCountData($response['data']) ; //统计金额
            $response['msg']    = 'success' ;
            $response['status'] = self::STATUS_SUCCESS ;

        } catch (\Exception $e) {
            $response['status']  =  self::STATUS_ERROR;
            if ($e->getMessage() == 10001) {
                $response['msg'] = '系统中没有查询到该用户...' ;
            } else {
                $response['msg'] = '网络延迟,请联系技术人员...' ;
            }
        }
        return $response ;
    }
    //获取
    private  function getAlreadyDetailData($page,$perPage,$date)
    {
        try{
            $uid  = Session::get('uid');
            $user = Db::table('k_user')->where(['uid'=>$uid])->find();
            if (empty($user)) {
                throw new \Exception(10001) ;
            }
            $data = Db::table('c_bet')->where(array('uid'=>$uid))
                ->where(['js'=>1])
                ->where(['adddate'=>$date])
                ->order('addtime desc')->paginate($perPage,false,['page'=>$page])->toArray() ;
            return $data ;
        } catch (\Exception $e){
            throw new \Exception($e->getMessage()) ;
        }
    }
    // 格式化已结注单详情彩票数据
    private function formatCpDetailData(&$data)
    {
        $result = [] ;
        try {
            //重新组合数据
            if ( !empty($data) ) {
                foreach ($data as $key=>$val) {
                    //第一列数据
                    $result[$key]['time']  = $val['addtime'] ; //日期
                    $result[$key]['type']  = $val['type']    ; //类型
                    $result[$key]['num']   = $val['qishu']    ; //期数
                    $result[$key]['id']    = $val['id']    ; //期数
                    $result[$key]['order'] = $val['did']    ; //单号

                    //第二列数据
                    $result[$key]['play_type'] = $val['mingxi_1'] ; //玩法分类
                    $result[$key]['content']   = $val['mingxi_2'] ; //投注内容
                    $result[$key]['odds']      = $val['odds'] ;     //赔率

                    //第三列数据
                    $result[$key]['bet_amount'] = $val['money'] ; //投注基恩
                    $result[$key]['profit']     = double_format($val["win"]+$val["commission"]) ; //盈利 = 中奖金额+反水
                    $result[$key]['profit']     = ($result[$key]['profit']>0) ?  $result[$key]['profit'] : "-{$val['money']}" ;
                }
            }
            return $result ;
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage()) ;
        }
    }
    //统计金额
    private  function  getCpDetailCountData(&$data)
    {
        $result['bet_amount'] = 0 ; //投注总金额
        $result['profit']        = 0 ; //总盈利
        try {
            if ( !empty($data) ) {
                foreach ($data as $key=>$val) {
                    $result['bet_amount'] = bcadd($result['bet_amount'],$val['bet_amount'],2) ;
                    $result['profit']     = bcadd($result['profit'],$val['profit'],2) ;
                }
            }
            $result['actual_win'] = bcsub($result['profit'],$result['bet_amount'],2 ) ; //实际盈利金额
            return $result ;
        } catch (\Exception $e) {
            throw new \Exception( $e->getMessage()) ;
        }
    }


    /**
     *  接口--获取彩票已结注单
     *
     */
    public function cpAlreadyRecord()
    {
        try{
            //定义数据返回格式
            $response['msg']     = '' ; //返回信息
            $response['status']  = 0  ; //请求状态
            $response['data']    = [] ; //数据

            //开始时间
            $time['start_date'] = ($this->request->param('start_date')) ;
            //结束时间
            $time['end_date'] = ($this->request->param('end_date')) ;
            $time['end_date'] = empty($time['end_date']) ? date('Y-m-d',time()) : $time['end_date'] ;

            //获取彩票已结注单数据
            $info    = $this->getCpAlreadyRecordData($time) ;
            $data    = json_decode(json_encode($info),true) ;

            $data    = $this->groupingDataByDate($data) ; //按日期对数据进行分组
            $data    = $this->formatCpAlreadyData($data) ; //格式化数据

            $timeArr = $this->combinationDateData($time) ; //组合出日期格式数组
            $data    = $this->replenishCpData($data,$timeArr) ; //补充数据
            $data    = array_values($data) ;

            //组合返回数据
            $response['data']   = $data ; //格式化数据
            $response['count']  = $this->countData($data) ; //统计数据
            $response['msg']    = 'success' ;
            $response['status'] = self::STATUS_SUCCESS ;

        } catch (\Exception $e) {
            $response['status']  =  self::STATUS_ERROR ;
            if ($e->getMessage() == 10001) {
                $response['msg'] = '系统中没有查询到该用户...' ;
            } else {
                $response['msg'] = '网络延迟,请联系技术人员...' ;
            }
        }
        return $response ;
    }
    //组合日期格式数组
    private  function combinationDateData($time)
    {
        $startTimes = strtotime($time['start_date']) ;
        $res        = [] ;
       for( $startTimes ;date('Y-m-d',$startTimes)<=$time['end_date'] ; $startTimes+=3600*24) {
           $res[date('Y-m-d',$startTimes)] = [] ;
       }
       return $res ;
    }
    //补充数据
    private  function replenishCpData($data,$timeArr)
    {
        $res = [] ;
        if (!empty($timeArr)) {
            //1.合并数组
            $res = array_merge($timeArr,$data) ;
            //2.没有数据的进行填充
            foreach ($res as $key=>$val) {
                if (empty($val)) {
                    $res[$key]['amount'] = 0 ;
                    $res[$key]['win'] = 0 ;
                    $res[$key]['date'] = $key;
                }
            }
        }
        return $res ;
    }
    //统计数据
    private  function countData($data =[])
    {
        $res['amount']     = 0 ; //总投注
        $res['win']        = 0 ; //总盈利
        $res['actual_win'] = 0 ; //实际盈利
        if (!empty($data)) {
            foreach ($data as $val) {
                $res['amount'] = bcadd( $res['amount'],$val['amount'],2 ) ;//总投注
                $res['win']    = bcadd( $res['win'],$val['win'],2 ) ;  //总盈利

            }
            $res['actual_win'] = bcsub($res['win'],$res['amount'],2)  ; //实际盈利金额
        }
        return $res ;
    }
    //按日期对数据进行分组
    private  function groupingDataByDate($data=[])
    {
        //2018-01-02  2018-01-03
        $res = [] ;
        if ( !empty($data) ) {
            foreach ($data as $key=>$val) {
                $res[$val['adddate']][] =  $val ;
            }
        }
        return $res ;
    }
    //格式化数据
    private  function formatCpAlreadyData($data=[])
    {
        $res = [] ;
        if (!empty($data)) {
            //第一次遍历,每个日期下的所有数据
             foreach ($data as $key=>$val) {
                 $amount = 0 ;
                 $win    = 0 ;
                 //第二次遍历,统计出当天的总投注额和输赢金额
                 foreach ($val as $k=>$v) {
                     $amount =  bcadd($amount,$v['money'],2) ;
                     //这里有两种情况,一种是赢,一种是输
                     if ($v['win']) {
                            $win = bcadd($win,$v['win'],2) ;  //加
                     } else {
                            $win = bcsub($win,$v['money'],2) ; //减
                     }
                 }
                 $res[$key]['amount'] = $amount;
                 $res[$key]['win']    = $win ;
                 $res[$key]['date']   = $key ;
             }
        }
        return $res ;
    }
    //获取已结注单数据
    private function getCpAlreadyRecordData($time)
    {
        try{
            $uid  = Session::get('uid');
            $user = Db::table('k_user')->where(['uid'=>$uid])->find() ;
            if (empty($user)) {
                throw new \Exception(10001) ;
            }
            $data = Db::table('c_bet')->where(array('uid'=>$uid))
                ->where(['js'=>1])
                ->where(['adddate' => ['egt',$time['start_date']]])
                ->where(['adddate' => ['elt',$time['end_date']]])
                ->field('id,did,uid,username,money,win,adddate,odds')
                ->order('addtime ASC')->select() ;

            return $data ;
        } catch (\Exception $e){
            throw new \Exception($e->getMessage()) ;
        }
    }

    
    /**
     *  接口--获取彩票未结注单
     *
     */
    public function cpRecord()
    {
        try{
            //定义数据返回格式
            $response['msg']     = '' ; //返回信息
            $response['status']  = 0  ; //请求状态
            $response['data']    = [] ; //数据
            $response['page']    = [] ; //分页数据

            //当前多少页
            $page = intval($this->request->param('page')) ;
            $page = ($page) ? $page : 1  ;
            //每页多少条
            $perPage = intval($this->request->param('per')) ;
            $perPage = ($perPage) ? $perPage : 20  ;

            //获取彩票未结注单数据
            $info = $this->getCpRecordData($page,$perPage);
            $data = json_decode(json_encode($info['data']),true) ;

            //组合返回数据
            $response['data']   = $this->formatCpRecordData($data)  ; //格式化数据
            $response['page']   = $this->getRecordPageData($info)   ; //组合分页返回数据
            $response['count']  = $this->getCpRecordCountData($response['data']) ; //统计金额
            $response['msg']    = 'success' ;
            $response['status'] = self::STATUS_SUCCESS ;

        } catch (\Exception $e) {
            $response['status']  =  self::STATUS_ERROR;
            if ($e->getMessage() == 10001) {
                $response['msg'] = '系统中没有查询到该用户...' ;
            } else {
                $response['msg'] = '网络延迟,请联系技术人员...' ;
            }
        }
        return $response ;
    }
    //获取彩票未结注单数据
    private  function getCpRecordData($page,$perPage)
    {
      try{
          $uid  = Session::get('uid');
          $user = Db::table('k_user')->where(['uid'=>$uid])->find();
          if (empty($user)) {
              throw new \Exception(10001) ;
          }
          $data = Db::table('c_bet')->where(array('uid'=>$uid))->where(['js'=>0])->order('addtime desc')->paginate($perPage,false,['page'=>$page])->toArray() ;
          return $data ;
      } catch (\Exception $e){
          throw new \Exception($e->getMessage()) ;
      }
    }
    // 格式化彩票数据
    private function formatCpRecordData(&$data)
    {
        $result = [] ;
        try {
            //重新组合数据
            if ( !empty($data) ) {
                foreach ($data as $key=>$val) {
                    //第一列数据
                    $result[$key]['time']  = $val['addtime'] ; //日期
                    $result[$key]['type']  = $val['type']    ; //类型
                    $result[$key]['num']   = $val['qishu']    ; //期数
                    $result[$key]['id']    = $val['id']    ; //期数
                    $result[$key]['order'] = $val['did']    ; //单号

                    //第二列数据
                    $result[$key]['play_type'] = $val['mingxi_1'] ; //玩法分类
                    $result[$key]['content']   = $val['mingxi_2'] ; //投注内容
                    $result[$key]['odds']      = $val['odds'] ;     //赔率

                    //第三列数据
                    $result[$key]['bet_amount'] = $val['money'] ; //投注基恩
                    //根据快钱玩法和官方玩法的不同,计算出可盈金额
                    if ( $val['gfwf'] ) {
                        //官方玩法 可盈金额 =  投注次数 * 倍数 * (计价单位/2)  因为计价单位的基本单位是2为单位
                        $win = $val['actionNum'] * $val['beiShu'] * $val['odds'] * ($val['mode']/2) ;
                    } else {
                        //快钱玩法
                        $win = bcmul($val['money'], $val['odds'],2) ; //快钱玩法
                    }
                    $result[$key]['profit'] = double_format(bcadd($win,$val["commission"],2) ) ;

                }
            }
            return $result ;
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage()) ;
        }
    }
    //统计金额
    private  function  getCpRecordCountData(&$data)
    {
        $result['bet']    = 0 ; //投注总金额
        $result['profit'] = 0 ; //总盈利
        try {
            if ( !empty($data) ) {
                foreach ($data as $key=>$val) {
                    $result['bet']    =  bcadd($result['bet'],$val['bet_amount'],2) ;
                    $result['profit'] =  bcadd($result['profit'],$val['profit'],2) ;
                }
            }

            return $result ;
        } catch (\Exception $e) {
            throw new \Exception( $e->getMessage()) ;
        }
    }

    
    public function tzhistory(){ //国家彩票游戏
    	$uid = Session::get('uid');
    	$user = Db::table('k_user')->where(array('uid'=>$uid))->find();
    	$type = isset($_GET['type']) ? $_GET['type'] : 'cqssc';
    	$d = isset($_GET['d']) ? $_GET['d'] : date("Y-m-d",time()-7*3600*24);
    	$ed = isset($_GET['ed']) ? $_GET['ed'] : date("Y-m-d");
    	$ed = isset($_GET['ed']) ? $_GET['ed'] : date("Y-m-d");
    	$n = isset($_GET['n']) ? $_GET['n'] : 'all';
    	$p = isset($_GET['p']) ? $_GET['p'] : 1;
    	$arr = array(
    		'type'=>$type,
    		'd'=>$d,
    		'ed'=>$ed,
    		'n'=>$n,
    		'p'=>$p
    	);
     	if($n != 'all' && $n){
     		$where = ' uid = '.$user['uid'].' and addtime >= '."'$d'".' and addtime <= '."'$ed'".' and type =' .$n;
    	}else {
    		$where = ' uid = '.$user['uid'].' and addtime >= '."'$d'".' and addtime <= '."'$ed'";
    	} 
    	$data = Db::table('c_bet_lt')->where($where)->order('addtime desc')->select();
    	
    	//$this->assign('user',$user);
    	$this->assign('arr',$arr);
    	$this->assign('data',$data);
    	return $this->fetch('tzhistory');
    }


    /**
     * 接口--报表历史记录
     * @return mixed
     */
    public function reportHistory()
    {
        $response['msg']     = '';
        $response['status']  = self::STATUS_ERROR;
        $response['data']['data']  = []; //数据
        $response['data']['count'] = []; //统计数据

        try {
            date_default_timezone_set('Asia/Shanghai');
            $uid = session("uid");
            $week = $this->request->param('week') ; //展示几周数据 默认展示一周
            $week = ($week) ? $week : 1 ;

            $day = input('day/d',($week*7) );//显示7天
            $day = $day - 1;
            if($day<0)$day = 0;
            $etime	= date("Y-m-d H:i:s");//'2017-08-20';//
            $stime	= date("Y-m-d",strtotime("$etime -$day day"));

            $result = [];
            for($i=0;$i<=$day;$i++){
                $time	=	date("Y-m-d",strtotime("$etime -$i day"));
                $result[$time] = ['ds'=>0,'ds_tz'=>0,'cg'=>0,'cg_tz'=>0,'cp'=>0,'cp_tz'=>0,];
                $result[$time]['name'] = getWeek(date("w",time()-$i*86400));
//                if(($i%2)==0) $result[$time]['bgcolor']="#FFFFFF";
//                else $result[$time]['bgcolor']="#F5F5F5";
            }

            //反水 commissioned,commission(体育)
            $data = db('k_bet')
                //->where('uid',$uid)
                ->where('status','IN',[1,2,4,5])//1,4赢,2,5输
                ->where('bet_time','BETWEEN',[$stime,$etime])
                ->where('uid','eq',$uid)
                ->group('bet_time2')
                ->field('date(bet_time) as bet_time2,sum(win-bet_money+IFNULL(commission,0)) as y, sum(bet_money) as tz')
                ->order('bet_time2 desc')
                ->select();

            foreach($data as $row){
                $result[$row['bet_time2']]['ds'] = $row['y'];
                $result[$row['bet_time2']]['ds_tz'] = $row['tz'];
            }

            //反水isfs,fs(串关)
            $data = db('k_bet_cg_group')
                //->where('uid',$uid)
                ->where('status',1)
                ->where('bet_time','BETWEEN',[$stime,$etime])
                ->where('uid','eq',$uid)
                ->group('bet_time2')
                ->field('date(bet_time) as bet_time2,sum(win-bet_money+IFNULL(fs,0)) as y, sum(bet_money) as tz')
                ->order('bet_time2 desc')
                ->select() ;

            foreach($data as $row){
                $result[$row['bet_time2']]['cg'] = $row['y'];
                $result[$row['bet_time2']]['cg_tz'] = $row['tz'];
            }
            //反水 commissioned,commission(彩票)
            $data = db('c_bet')
                //->where('uid',$uid)
                ->where('js',1)//已结束
                ->where('addtime','BETWEEN',[$stime,$etime])
                ->where('uid','eq',$uid)
                ->group('bet_time2')
                ->field('date(addtime) as bet_time2,sum(win-money+IFNULL(commission,0)) as y, sum(money) as tz')
                ->order('bet_time2 desc')
               ->select();

            foreach($data as $row){
                $result[$row['bet_time2']]['cp'] = $row['y'];
                $result[$row['bet_time2']]['cp_tz'] = $row['tz'];
            }

            //平台N天内的总输赢
            $sum = [];

            foreach($result as $key => $value){
                @ $result[$key]['sum'] = $result[$key]['ds'] + $result[$key]['cg'] + $result[$key]['cp'];
                @ $sum['ds'] += $result[$key]['ds'];
                @ $sum['ds_tz'] += $result[$key]['ds_tz'];
                @ $sum['cg'] += $result[$key]['cg'];
                @ $sum['cg_tz'] += $result[$key]['cg_tz'];
                @ $sum['cp'] += $result[$key]['cp'];
                @ $sum['cp_tz'] += $result[$key]['cp_tz'];
                @ $sum['sum'] += $result[$key]['sum'];
            }

            $response['msg']    = 'success' ;
            $response['status'] = self::STATUS_SUCCESS ;
            $response['data']   = $this->formatReportHistoryData($result) ;
            $response['count']  = $sum ;

        } catch (\Exception $e) {
           $response['status'] = self::STATUS_ERROR ;
           $response['msg'] = '网络延迟,请联系技术人员处理..' ;
        }

        return $response ;
    }
    //格式化历史报表数据
    private  function formatReportHistoryData($data)
    {
        $result = [] ;
        $i = 0 ;
        try {
            if (!empty($data)) {
                foreach ($data as $key=>$val) {
                    $result[$i] =  $val ;
                    $result[$i]['date'] = $key ;
                    $i++ ;
                }
            }
          return $result ;
        } catch (\Exception $e) {
          throw new \Exception($e->getMessage()) ;
        }
    }


    public function report_ds(){
        date_default_timezone_set('Asia/Shanghai');
        $uid	=	session("uid");
        $riqi = input('riqi');
        
        $data = ['riqi'=>$riqi,];
        $rules = ['riqi'=>'dateFormat:Y-m-d',];
        $msg = ['riqi.dateFormat'=>'日期参数格式错误',];
        $validate = new \Think\Validate($rules,$msg);
        if(! $validate->check($data))
        {
            $error = $validate->getError();
            $this->error($error);
        }

        $list = db('k_bet')
        ->where('uid',$uid)
        ->where('status','IN',[1,2,4,5])//1,4赢,2,5输
        ->where('bet_time','BETWEEN',[$riqi,$riqi." 23:59:59"])
        //->fetchSql()->select();
        ->order('bet_time desc')->paginate();
        //dump($list);return;
        $data = $list->all();
        foreach($data as $key=>$value){
            $data[$key]['jine'] = $value['win']+$value['commission']-$value['bet_money'];
        }
        //dump($data);return;        
        $this->assign('data',$data);
        $this->assign('list',$list);

        $count = db('k_bet')
        //->where('uid',$uid)
        ->where('status','IN',[1,2,4,5])//1,4赢,2,5输
        ->where('bet_time','BETWEEN',[$riqi,$riqi." 23:59:59"])
        ->count();
        
        $sum = db('k_bet')
        //->where('uid',$uid)
        ->where('status','IN',[1,2,4,5])//1,4赢,2,5输
        ->where('bet_time','BETWEEN',[$riqi,$riqi." 23:59:59"])
        ->sum('win+commission-bet_money');
  
        $this->assign('count',$count);
        $this->assign('sum',$sum);
        
        return $this->fetch();
    }
    
    public function report_cg(){
        date_default_timezone_set('Asia/Shanghai');
        $uid	=	session("uid");
        $riqi = input('riqi');
        
        $data = ['riqi'=>$riqi,];
        $rules = ['riqi'=>'dateFormat:Y-m-d',];
        $msg = ['riqi.dateFormat'=>'日期参数格式错误',];
        $validate = new \Think\Validate($rules,$msg);
        if(! $validate->check($data))
        {
            $error = $validate->getError();
            $this->error($error);
        }
	
        $field =
        '`k_bet_cg_group`.`gid`       AS `gid`,
        `k_bet_cg_group`.`bet_time`  AS `gbet_time`,
        `k_bet_cg_group`.`bet_money` AS `bet_money`,
        `k_bet_cg_group`.`cg_count`  AS `cg_count`,
        `k_bet_cg_group`.`bet_win`   AS `bet_win`,
        `k_bet_cg_group`.`win`       AS `win`,
        `k_bet_cg`.`bid`             AS `bid`,
        `k_bet_cg`.`bet_time`        AS `bet_time`,
        `k_bet_cg`.`bet_info`        AS `bet_info`,
        `k_bet_cg`.`match_name`      AS `match_name`,
        `k_bet_cg`.`master_guest`    AS `master_guest`,
        `k_bet_cg`.`MB_Inball`       AS `MB_Inball`,
        `k_bet_cg`.`TG_Inball`       AS `TG_Inball`,
        `k_bet_cg_group`.`uid`       AS `uid`,
        `k_bet_cg_group`.`fs`        AS `fs`,
        `k_bet_cg`.`match_dxgg`      AS `match_dxgg`,
        `k_bet_cg`.`match_rgg`       AS `match_rgg`,
        `k_bet_cg`.`match_showtype`  AS `match_showtype`,
        `k_bet_cg`.`point_column`    AS `point_column`,
        `k_bet_cg`.`ball_sort`       AS `ball_sort`,
        `k_bet_cg`.`bet_point`       AS `bet_point`,
        `k_bet_cg`.`master`          AS `master`,
        `k_bet_cg`.`guest`           AS `guest`,
        `k_bet_cg_group`.`status`    AS `status`,
        `k_bet_cg`.`status`          AS `match_status`';
            
        $betcg = db('k_bet_cg_group')
        ->join('k_bet_cg','k_bet_cg.gid = k_bet_cg_group.gid')
        ->where('k_bet_cg_group.uid',$uid)
        ->where('k_bet_cg_group.status',1)//已结束
        ->where('k_bet_cg_group.bet_time','BETWEEN',[$riqi,$riqi." 23:59:59"])
        ->field($field)
        //->fetchSql()->select();
        ->order('bet_time desc')->select();
        //dump($betcg);return;
        foreach($betcg as $key=>$value){
            $betcg[$key]['jine'] = $value['win']+$value['fs']-$value['bet_money'];
        }
        
        $this->assign('betcg',$betcg);
        $this->assign('ky',0);
        $this->assign('bet_money',0);
        $this->assign('bgcolor','');
        $this->assign('current','');
  
        $this->assign('line_count','0');
        return $this->fetch();        
    }
    
    public function report_cp(){
        date_default_timezone_set('Asia/Shanghai');
        $uid	=	session("uid");
        $user   =   db('k_user')->where('uid',$uid)->find();
        $riqi  = input('riqi');

        $data = ['riqi'=>$riqi,];
        $rules = ['riqi'=>'dateFormat:Y-m-d',];
        $msg = ['riqi.dateFormat'=>'日期参数格式错误',];
        $validate = new \Think\Validate($rules,$msg);
        if(! $validate->check($data))
        {
            $error = $validate->getError();
            $this->error($error);
        }
	
        $cbet = db('c_bet')
        ->where('username',$user['username'])
        ->where('js',1)//已结束
        ->where('addtime','BETWEEN',[$riqi,$riqi." 23:59:59"])
        //->fetchSql()->select();
        ->order('addtime desc')->select();
        //dump($cbet);return;
        foreach($cbet as $key=>$value){
            $cbet[$key]['jine'] = $value['win']+$value['commission']-$value['money'];
        }
      
        $this->assign('cbet',$cbet);
        
        $count = db('c_bet')
        ->where('username',$user['username'])
        ->where('js',1)//已结束
        ->where('addtime','BETWEEN',[$riqi,$riqi." 23:59:59"])
        ->count();
        
        $sum = db('c_bet')
        ->where('username',$user['username'])
        ->where('js',1)//已结束
        ->where('addtime','BETWEEN',[$riqi,$riqi." 23:59:59"])
        ->sum('win+commission-money');
      
        $this->assign('count',$count);
        $this->assign('sum',$sum);
        
        return $this->fetch();
    }

//    //站内公告
//    public function sms(){
//    	$uid = Session::get('uid');
//    	$user = Db::table('k_user')->where(array('uid'=>$uid))->find();
//    	$usermsg = Db::table('k_user_msg')->where(array('uid'=>$user['uid']))->order('msg_time desc')->select();
//
//    	$num = Db::table('k_user_msg')->where(array('uid'=>$user['uid']))->count();//总数
//    	$usermsg = Db::table('k_user_msg')->where(array('uid'=>$user['uid']))->order('msg_time desc')->paginate(15,$num);
//    	$page = $usermsg->render();
//
//    	$this->assign('usermsg',$usermsg);
//    	$this->assign('user',$user);
//    	$this->assign('page',$page);
//    	return $this->fetch('sms');
//    }

    /**
     *  接口-- 获取未读信息数据
     */
    public function unreadMessage()
    {
        try{
            //定义数据返回格式
            $response['msg']    = '' ; //返回信息
            $response['status'] = self::STATUS_ERROR  ; //请求状态
            $response['data']   = [] ; //数据
            $response['page']   = [] ; //分页数据

            //当前多少页
            $page = intval($this->request->param('page')) ;
            $page = ($page) ? $page : 1  ;
            //每页多少条
            $perPage = intval($this->request->param('per')) ;
            $perPage = ($perPage) ? $perPage : 20  ;

            //获取未读数据
            $info = $this->getUnreadMessageData($page,$perPage);
            $data = json_decode(json_encode($info['data']),true) ;

            //组合返回数据
            $response['msg']    = 'success' ;
            $response['status'] = self::STATUS_SUCCESS ;
            $response['data']   = $this->formatUnreadMessageData($data); //格式化数据
            $response['page']   = $this->getRecordPageData($info) ; //组合分页返回数据

        } catch (\Exception $e) {
            $response['status'] =  self::STATUS_ERROR ;
            if ($e->getMessage() == 10001) {
                $response['msg'] = '系统中没有查询到该用户...' ;
            } else {
                $response['msg'] = '网络延迟,请联系技术人员...' ;
            }
        }
        return $response ;
    }
    //获取未读源数据
    private  function  getUnreadMessageData($page,$perPage)
    {
        try{
            $uid = Session::get('uid');
            $user = Db::table('k_user')->where(array('uid'=>$uid))->find();
            if ( empty($user) ) {
                throw new \Exception(10001) ;
            }
            $data = Db::table('k_user_msg')->where( ['uid'=>$user['uid'],'islook'=>0] )->field('msg_id,msg_title,msg_time')->order('msg_time desc')->paginate($perPage,false,['page'=>$page])->toArray() ;

            return $data ;
        } catch (\Exception $e){
            throw new \Exception($e->getMessage());
        }
    }
    //格式化未读数据
    private  function  formatUnreadMessageData(&$data)
    {
       $result = [] ;
       try {
            if (!empty($data)) {
                foreach ($data as $key=>$val) {
                    $result[$key]['id']     = $val['msg_id'] ;
                    $result[$key]['title']  = $val['msg_title'] ;
                    $result[$key]['time']   = $val['msg_time'] ;
                }
                unset($data);
            }
       }catch (\Exception $e) {
           throw new \Exception( $e->getMessage());
       }
       return $result ;
    }


    /**
     * 根据ID获取对应的未读信息接口
     *
     */
    public function getMessageContent()
    {
        //定义格式
        $response['msg']    = '' ;
        $response['status'] = self::STATUS_ERROR ;
        $response['data']   = [] ;

        try {
            $id = $this->request->param('id') ;
            if (empty($id)) {
                throw new \Exception(100001) ;
            }

            $data = $this->getMessageContentById($id) ; //获取数据
            $response['status'] = self::STATUS_SUCCESS ;
            $response['data'] = $this->formatMessageContent($data) ; //格式化数据

        } catch (\Exception $e) {
            $response['status'] = self::STATUS_ERROR ;
            if ($e->getMessage() == 100001) {
                $response['msg'] = '必须传入id' ;
            }
        }

        return $response ;
    }
    //根据id获取对应的未读信息
    private  function getMessageContentById($id)
    {
        try {
            return Db::table('k_user_msg')->find($id) ;
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage()) ;
        }
    }
    //格式化获取信息
    private  function formatMessageContent($data)
    {
        $result = [] ;
        try{
            if(!empty($data)) {
                $result['id']    = $data['msg_id'] ;
                $result['from']  = $data['msg_from'] ;
                $result['uid']   = $data['uid'] ;
                $result['title'] = $data['msg_title'] ;
                $result['info']  = $data['msg_info'] ;
                $result['time']  = $data['msg_time'] ;
            }
        } catch (\Exception $e){
            throw new \Exception( $e->getMessage());
        }
        return $result ;
    }

    /**
     *  将未读信息状态改为已读
     *  @return mixed
     */
    public function changeMessageStatus()
    {
        //定义格式
        $response['msg']    = '' ;
        $response['status'] = self::STATUS_ERROR ;

        try {
            $id = $this->request->param('id') ;
            if (empty($id)) {
                throw new \Exception(100001) ;
            }
            //更改状态
            if (!Db::table('k_user_msg')->where('msg_id',$id)->update(['islook'=>1])) {
                throw new \Exception(100002) ;
            }
            $response['status'] = self::STATUS_SUCCESS ;
            $response['msg'] = 'success' ;

        } catch (\Exception $e) {
            //错误处理
            $response['status'] = self::STATUS_ERROR ;
            if ($e->getMessage() == 100001) {
                $response['msg'] = '必须传入id' ;
            } elseif ($e->getMessage() == 100002) {
                $response['msg'] = '修改未读信息状态失败' ;
            } else {
                $response['msg'] = $e->getMessage() ;
             }
        }
        return $response ;
    }

    /**
     *  删除信息
     *
     *  @return mixed
     */
    public function delMessage()
    {
        //定义格式
        $response['msg']    = '' ;
        $response['status'] = self::STATUS_ERROR ;

        try {
            $id    = $this->request->param('id') ;
            if (empty($id)) {
                throw new \Exception(100001) ;
            }
            $idArr = explode(',',$id)  ;
            $idArr = array_filter($idArr) ; //过滤空值
            $ids   = implode(',',$idArr) ;

            //删除
            if (!Db::table('k_user_msg')->where("msg_id in ({$ids})")->delete()) {
                throw new \Exception(100002) ;
            }
            $response['status'] = self::STATUS_SUCCESS ;
            $response['msg'] = 'success' ;

        } catch (\Exception $e) {
            //错误处理
            $response['status'] = self::STATUS_ERROR ;
            if ($e->getMessage() == 100001) {
                $response['msg'] = '必须传入id' ;
            } elseif ($e->getMessage() == 100002) {
                $response['msg'] = '删除数据失败' ;
            } else {
                $response['msg'] = $e->getMessage() ;
            }
        }
        return $response ;
    }

    
    public function smsshow($id=0){
        $uid = Session('uid');
        $where = array(
            'uid' =>['eq',$uid],
            'msg_id' => ['eq',$id]
        );
        $data = ['islook'=>1];
        Db::table('k_user_msg')->where($where)->update($data);
        $info = Db::table('k_user_msg')->where($where)->find();
        $this->assign('info',$info);
        return $this->fetch();
    }
    
    public function smsdel($id=0){
        $uid = Session('uid');
        $where = array(
            'uid'       => ['eq',$uid],
            'msg_id'    => ['eq',$id],
        );
        $info = Db::table('k_user_msg') ->where($where)->delete();
        if($info){
            $this->success('删除成功!',url('user/sms'));
        }else{
            $this->error('删除失败!',url('user/sms'));
        }
    }

    /**
     *  申请代理--接口
     */
    public function registerAgent()
    {
        $response['msg']    = '' ;
        $response['status'] = self::STATUS_ERROR ;
        date_default_timezone_set('PRC') ;

        try {
            $uid  = Session::get('uid') ;
            $user = Db::table('k_user')->where(array('uid'=>$uid))->find() ;
            if ( empty($user) ) { throw new \Exception(10001) ; }

            $where = "uid='".Session::get('uid')."' and add_time>='".date("Y-m-d")." 00:00:00' and add_time<='".date("Y-m-d")." 23:59:59'";
            $userdaili = Db::table('k_user_daili')->where($where)->find();
            //代理每天只能申请一次
            if ( $userdaili ) {
                throw new \Exception(10002) ;
            }
            $this->insertAgent() ; //代理申请数据入库

            $response['msg']    = '提款申请已经提交，等待财务人员给您转账。您可以到历史报表中查询您的取款状态！' ;
            $response['status'] = self::STATUS_SUCCESS ;

        } catch (\Exception $e) {
            //集中处理错误
            $response['status'] = self::STATUS_ERROR  ;
            if ($e->getMessage()==10001) {
                $response['msg'] = '用户信息为找到,或者该用户没有登录';
            } elseif ($e->getMessage()==10002) {
                $response['msg'] = '代理每天只能申请一次';
            } elseif ($e->getMessage()==10003) {
                $response['msg'] = '由于网络堵塞，本次申请提款失败。请您稍候再试，或联系在线客服。' ;
            } else {
                $response['msg'] = $e->getMessage() ;
            }
        }

        return $response ;
    }
    // 代理数据入库
    private  function insertAgent()
    {
        Db::startTrans();//开启事务
        try {
            $data['uid']    = Session::get('uid');
            $data['r_name'] = $this->request->param("r_name");
            $data['mobile'] = $this->request->param("mobile");
            $data['email']  = $this->request->param("email") ;
            $data['about']  = $this->request->param("about") ;

            //事务成功
            $daili = Db::table('k_user_daili')->insert($data) ;
            Db::commit() ;

        } catch(Exception $e) {
            Db::rollback();  //数据回滚
            throw new \Exception(10003) ;
        }
    }


    /**
     * 申请推广地址--接口
     */
    public function agent()
    {
        $response['msg']    = '' ;
        $response['status'] = self::STATUS_ERROR ;
        $response['url']    = '' ; //数据

        try{
            $uid  = Session::get('uid') ;
            $user = Db::table('k_user')->where(array('uid'=>$uid))->find() ;
            if (empty($user)) {
                throw new \Exception(10001) ;
            }

            //拼接申请推广地址
            $url       = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'].'/?f='.$user['username'];
            $userdaili = Db::table('k_user_daili')->where(array('uid'=>$uid))->find() ;

            //是否申请代理判断
            if ( empty($userdaili) ) {
                throw new \Exception(10002) ;
            }

            if ($userdaili['status'] != 1) {
                throw new \Exception(10003) ;
            }

            $response['msg']    = 'success' ;
            $response['status'] = self::STATUS_SUCCESS ;
            $response['url']    = $url ;

        } catch (\Exception $e) {
            $response['status'] = self::STATUS_ERROR ;
            if ( $e->getMessage()==10001 ) {
                $response['msg'] = '未找到该用户,或者未登录' ;
            } elseif ($e->getMessage()==10002) {
                $response['status'] = 2 ;
                $response['msg']    = '你还未申请代理' ;
            } elseif ($e->getMessage()==10003) {
                $response['status'] = 3 ;
                $response['msg']    = '申请正在审核中,请耐心等待' ;
            } else {
                $response['msg'] = $e->getMessage() ;
            }
        }
        return $response ;
    }

    /**
     *  绑定银行卡--接口
     */
    public function bindBankCard()
    {
        $response['msg']    = '' ;
        $response['status'] = self::STATUS_ERROR ;

        try {
                $bank['pay_name']    = $this->request->param('name') ; //开户姓名
                $bank['pay_card']    = $this->request->param('bank') ; //开户银行
                $bank['pay_num']     = $this->request->param('num') ; //银行卡号
                $bank['pay_address'] = $this->request->param('address') ; //开户行地址
                $qkPass              = $this->request->param('qkPass') ; // 取款密码
                $qkPassAgain         = $this->request->param('qkPassAgain') ; // 确认取款密码
                $ask                 = $this->request->param('ask') ; // 密保问题
                $answer              = $this->request->param('answer') ; // 密保答案
                $uid                 =  Session::get('uid') ; //用户ID
                $bank['qk_pwd']      = md5($qkPass) ;
                $this->verifyBankInfo($bank) ; //验证数据

                $user = Db::table('k_user')->where('uid',$uid)->find() ;
                if ( empty($user) ) { throw new \Exception(10001) ; }

                if ($qkPass != $qkPassAgain) {throw new \Exception(10002);} //判断用户设置的取款密码是否一致

                if (!empty($user['pay_num'])) { throw new \Exception(10003) ; } //是否已绑定银行卡

             if (!empty($user['answer'])) {
                 if ($user['ask'] != $ask) {  throw new \Exception(10004) ;} //密保问题是否正确
                 if ($user['answer'] != $answer) {  throw new \Exception(10006) ;} //密保答案是否正确
             }

                //数据入库失败
                if (!Db::table('k_user')->where(array('uid'=>$uid))->update($bank)) {
                    throw new \Exception(10010) ;
                }

                //绑定成功
                $response['msg']    = 'success' ;
                $response['status'] = self::STATUS_SUCCESS ;

        } catch (\Exception $e) {
            //错误处理
            $response['status'] = self::STATUS_ERROR ;
             if ($e->getMessage() == 10005) {
                 $response['msg'] = '银行卡相关信息不能为空' ;
             } elseif ($e->getMessage()==10001) {
                 $response['msg'] = '没找到该用户,或者用户未登录' ;
             } elseif ($e->getMessage() == 10002) {
                 $response['msg'] = '两次输入的取款密码不一致' ;
             } elseif ($e->getMessage() == 10003) {
                 $response['status']  = 2 ;
                 $response['msg'] = '已经绑定银行卡,请勿重复绑定' ;
             } elseif ($e->getMessage() == 10004) {
                 $response['msg'] = '密保问题不正确' ;
             } elseif ($e->getMessage() == 10006) {
                 $response['msg'] = '密保答案不正确' ;
             } elseif ($e->getMessage()==10010) {
                 $response['msg'] = '网络延迟,请联系客服或技术人员处理' ;
             } else {
                 $response['msg'] = $e->getMessage() ;
             }
        }
        return $response ;
    }
    //效验银行卡数据
    private function verifyBankInfo($bank=[])
    {
        try {
            if ( empty($bank) ) {
                throw new \Exception('数据不能为空') ;
             }
            //银行卡相关数据不能为空
             foreach ($bank as $val) {
                if (empty($val)) {
                    throw new \Exception('数据不能为空') ;
                    break ;
                }
             }

        } catch (\Exception $e) {
            throw new \Exception(10005) ;
        }
    }


    /**
     *  修改用户登录密码 -- 接口
     *
     *  返回状态 0:修改成功  1:修改失败
     */
    public function editLoginPwd()
    {
        $response['msg']    = '' ;
        $response['status'] = self::STATUS_ERROR ;

        try {
            $oldPass      = trim($this->request->param('oldPass'))     ; //原始密码
            $newPass      = trim($this->request->param('newPass'))     ; //新密码
            $newPassAgain = trim($this->request->param('newPassAgain')); //新密码再一次输入
            $uid          = Session::get('uid') ;

            //两次新密码不一致
            if ( $newPass != $newPassAgain) {
                throw new \Exception(10001) ;
            }

            //检查原密码
            $user = Db::table('k_user')->where(array('uid'=>$uid,'password'=>md5($oldPass)))->find();
            if (empty($user)) {
                throw new \Exception(10002) ;
            }

            //修改登录密码
            if ( !Db::table('k_user')->where(array('uid'=>$uid))->update(['password'=>md5($newPass)]) ) {
                throw new \Exception(10003) ;
            }

            //修改成功
            $response['status'] = self::STATUS_SUCCESS ;
            $response['msg']    = 'success' ;

        } catch (\Exception $e) {
            $response['status'] = self::STATUS_ERROR ;
            if ($e->getMessage()==10001) {
                $response['msg'] = '两次输入新密码不一致' ;
            } elseif ($e->getMessage()==10002) {
                $response['msg'] = '原密码输入错误' ;
            } elseif ($e->getMessage()==10003) {
                $response['msg'] = '修改失败,请重试' ;
            } else {
                $response['msg'] = '网络延迟请重试,或联系客服人员或技术人员处理' ;
            }
        }

        return $response;
    }

    /**
     *  修改用户取款密码 -- 接口
     *
     *  返回状态 0:修改成功  1:修改失败
     */
    public function editDrawMoneyPwd()
    {
        $response['msg']    = '' ;
        $response['status'] = self::STATUS_ERROR ;

        try {
            $oldPass      = trim($this->request->param('oldPass'))     ; //原始密码
            $newPass      = trim($this->request->param('newPass'))     ; //新密码
            $newPassAgain = trim($this->request->param('newPassAgain')); //新密码再一次输入
            $uid          = Session::get('uid') ;

            //两次新密码不一致
            if ( $newPass != $newPassAgain) {
                throw new \Exception(10001) ;
            }

            //检查原密码
            $user = Db::table('k_user')->where(array('uid'=>$uid,'qk_pwd'=>md5($oldPass)))->find();
            if (empty($user)) {
                throw new \Exception(10002) ;
            }

            //修改取款密码
            if ( !Db::table('k_user')->where(array('uid'=>$uid))->update(['qk_pwd'=>md5($newPass)]) ) {
                throw new \Exception(10003) ;
            }

            //修改成功
            $response['status'] = self::STATUS_SUCCESS ;
            $response['msg']    = 'success' ;

        } catch (\Exception $e) {
            $response['status'] = self::STATUS_ERROR ;
            if ($e->getMessage()==10001) {
                $response['msg'] = '两次输入新密码不一致' ;
            } elseif ($e->getMessage()==10002) {
                $response['msg'] = '原密码输入错误' ;
            } elseif ($e->getMessage()==10003) {
                $response['msg'] = '修改失败,请重试' ;
            } else {
                $response['msg'] = '网络延迟请重试,或联系客服人员或技术人员处理' ;
            }
        }

        return $response;
    }

    public function ag_user(){ //下级列表
        date_default_timezone_set('PRC');
    	$request = \think\Request::instance();
    	$username = $request->param('username') ? $request->param('username') : '';
    	$data_k = $request->param('data_k') ? $request->param('data_k') : '';
    	$data_o = $request->param('data_o') ? $request->param('data_o') : '';
    	$month = date("Y-m");
    	if($username){
    	    $where['username'] = ['=',$username];
    	}
    	return $this->fetch('ag_user');
    }
    
    public function ag_data(){ //报表统计
    	
    	return $this->fetch('ag_data');
    }


    public function logout(){
        session(null);
        return $this->api_success('退出成功!');
    }


}

?>
