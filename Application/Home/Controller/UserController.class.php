<?php

namespace Home\Controller;

/**
 * 用户
 * @author   Devil
 * @blog     http://gong.gg/
 * @version  0.0.1
 * @datetime 2017-03-02T22:48:35+0800
 */
class UserController extends CommonController
{
	/**
	 * [_initialize 前置操作-继承公共前置方法]
	 * @author   Devil
	 * @blog     http://gong.gg/
	 * @version  0.0.1
	 * @datetime 2017-03-02T22:48:35+0800
	 */
	public function _initialize()
	{
		// 调用父类前置方法
		parent::_initialize();
	}

	/**
	 * [GetrefererUrl 获取上一个页面地址]
	 * @author   Devil
	 * @blog     http://gong.gg/
	 * @version  0.0.1
	 * @datetime 2017-03-09T15:46:16+0800
	 */
	private function GetrefererUrl()
	{
		// 上一个页面, 空则用户中心
		if(empty($_SERVER['HTTP_REFERER']))
		{
			$referer_url = U('Home/User/Index');
		} else {
			if(strpos($_SERVER['HTTP_REFERER'], 'RegInfo') !== false || strpos($_SERVER['HTTP_REFERER'], 'LoginInfo') !== false)
			{
				$referer_url = U('Home/User/Index');
			} else {
				$referer_url = $_SERVER['HTTP_REFERER'];
			}
		}
		return $referer_url;
	}

	/**
	 * [Index 用户中心]
	 * @author   Devil
	 * @blog     http://gong.gg/
	 * @version  0.0.1
	 * @datetime 2017-03-02T22:48:35+0800
	 */
	public function Index()
	{
		$this->display('Index');
	}

	/**
	 * [RegInfo 用户注册页面]
	 * @author   Devil
	 * @blog     http://gong.gg/
	 * @version  0.0.1
	 * @datetime 2017-03-02T22:48:35+0800
	 */
	public function RegInfo()
	{
		//$sms = new \My\Email();

		if(MyC('home_user_reg_state') == 1)
		{
			if(empty($this->user))
			{
				$this->assign('referer_url', $this->GetrefererUrl());
				$this->display('RegInfo');
			} else {
				$this->assign('msg', L('common_reg_already_had_tips'));
				$this->display('/Public/TipsError');
			}
		} else {
			$this->assign('msg', L('common_close_user_reg_tips'));
			$this->display('/Public/TipsError');
		}
	}

	/**
	 * [Reg 用户注册]
	 * @author   Devil
	 * @blog     http://gong.gg/
	 * @version  0.0.1
	 * @datetime 2017-03-07T00:08:36+0800
	 */
	public function Reg()
	{
		// 是否开启用户注册
		if(MyC('home_user_reg_state') != 1)
		{
			$this->error(L('common_close_user_reg_tips'));
		}

		// 是否ajax请求
		if(!IS_AJAX)
		{
			$this->error(L('common_unauthorized_access'));
		}

		// 手机号码是否已存在
		if($this->IsExistMobile())
		{
			$this->ajaxReturn(L('common_mobile_exist_error'), -1);
		}

		// 验证码校验
		$verify_param = array(
				'key_prefix' => 'reg',
				'expire_time' => MyC('common_verify_expire_time')
			);
		$sms = new \My\Sms($verify_param);
		if(!$sms->CheckExpire())
		{
			$this->ajaxReturn(L('common_verify_expire'), -10);
		}
		if(!$sms->CheckCorrect(I('verify')))
		{
			$this->ajaxReturn(L('common_verify_error'), -11);
		}

		// 模型
		$m = D('User');

		// 数据自动校验
		if($m->create($_POST, 1))
		{
			// 额外数据处理
			$m->add_time	=	time();
			$m->upd_time	=	time();
			$m->salt 		=	GetNumberCode(6);
			$m->pwd 		=	LoginPwdEncryption(I('pwd'), $m->salt);

			// 数据添加
			$user_id = $m->add();
			if($user_id > 0)
			{
				// 清除验证码
				$sms->Remove();

				if($this->UserLoginRecord($user_id))
				{
					$this->ajaxReturn(L('common_reg_success'));
				}
				$this->ajaxReturn(L('common_reg_success_login_tips'));
			} else {
				$this->ajaxReturn(L('common_reg_error'), -100);
			}
		} else {
			$this->ajaxReturn($m->getError(), -1);
		}
	}

	/**
	 * [LoginInfo 用户登录页面]
	 * @author   Devil
	 * @blog     http://gong.gg/
	 * @version  0.0.1
	 * @datetime 2017-03-02T22:48:35+0800
	 */
	public function LoginInfo()
	{
		if(MyC('home_user_login_state') == 1)
		{
			if(empty($this->user))
			{
				$this->assign('referer_url', $this->GetrefererUrl());
				$this->display('LoginInfo');
			} else {
				$this->assign('msg', L('common_login_already_had_tips'));
				$this->display('/Public/TipsError');
			}
		} else {
			$this->assign('msg', L('common_close_user_login_tips'));
			$this->display('/Public/TipsError');
		}
	}

	/**
	 * [Login 用户登录]
	 * @author   Devil
	 * @blog     http://gong.gg/
	 * @version  0.0.1
	 * @datetime 2017-03-09T10:57:31+0800
	 */
	public function Login()
	{
		// 是否开启用户登录
		if(MyC('home_user_login_state') != 1)
		{
			$this->error(L('common_close_user_login_tips'));
		}

		// 是否ajax请求
		if(!IS_AJAX)
		{
			$this->error(L('common_unauthorized_access'));
		}

		// 登录帐号格式校验
		$account = I('account');
		if(!CheckMobile($account) && !CheckEmail($account))
		{
			$this->ajaxReturn(L('user_login_account_format'), -1);
		}

		// 密码
		$pwd = trim(I('pwd'));
		if(!CheckLoginPwd($pwd))
		{
			$this->ajaxReturn(L('user_reg_pwd_format'), -2);
		}

		// 获取用户账户信息
		$where = array('mobile' => $account, 'email' => $account, '_logic' => 'OR');
		$user = M('User')->field(array('id', 'pwd', 'salt'))->where($where)->find();
		if(empty($user))
		{
			$this->ajaxReturn(L('user_login_account_on_exist_error'), -3);
		}

		// 密码校验
		if(LoginPwdEncryption($pwd, $user['salt']) != $user['pwd'])
		{
			$this->ajaxReturn(L('user_common_pwd_error'), -4);
		}

		// 更新用户密码
		$salt = GetNumberCode(6);
		$data = array(
				'pwd'		=>	LoginPwdEncryption($pwd, $salt),
				'salt'		=>	$salt,
				'upd_time'	=>	time(),
			);
		if(M('User')->where(array('id'=>$user['id']))->save($data) !== false)
		{
			// 登录记录
			if($this->UserLoginRecord($user['id']))
			{
				$this->ajaxReturn(L('common_login_success'));
			}
		}		
		$this->ajaxReturn(L('common_login_invalid'), -100);
	}

	/**
	 * [UserLoginRecord 用户登录记录]
	 * @author   Devil
	 * @blog     http://gong.gg/
	 * @version  0.0.1
	 * @datetime 2017-03-09T11:37:43+0800
	 * @param    [int]     $user_id [用户id]
	 * @return   [boolean]          [记录成功true, 失败false]
	 */
	private function UserLoginRecord($user_id)
	{
		if(!empty($user_id))
		{
			$field = array('id', 'mobile', 'email', 'nickname', 'gender', 'add_time', 'upd_time');
			$user = M('User')->field($field)->find($user_id);
			if(!empty($user))
			{
				$_SESSION['user'] = $user;
				return !empty($_SESSION['user']);
			}
		}
		return false;
	}

	/**
	 * [RegVerifyEntry 用户注册-验证码显示]
	 * @author   Devil
	 * @blog     http://gong.gg/
	 * @version  0.0.1
	 * @datetime 2017-03-05T15:10:21+0800
	 */
	public function RegVerifyEntry()
	{
		// 是否开启用户注册
		if(MyC('home_user_reg_state') == 1)
		{
			$param = array(
					'width' => 100,
					'height' => 32,
					'key_prefix' => 'reg',
				);
			$verify = new \My\Verify($param);
			$verify->Entry();
		}
	}

	/**
	 * [RegSmsSend 用户注册-短信验证码发送]
	 * @author   Devil
	 * @blog     http://gong.gg/
	 * @version  0.0.1
	 * @datetime 2017-03-05T19:17:10+0800
	 */
	public function RegSmsSend()
	{
		// 是否开启用户注册
		if(MyC('home_user_reg_state') != 1)
		{
			$this->error(L('common_close_user_reg_tips'));
		}

		// 是否ajax请求
		if(!IS_AJAX)
		{
			$this->error(L('common_unauthorized_access'));
		}

		// 参数
		if(empty($_POST['mobile']))
		{
			$this->ajaxReturn(L('common_param_error'), -1);
		}

		// 手机号码格式
		if(!CheckMobile(I('mobile')))
		{
			$this->ajaxReturn(L('common_mobile_format_error'), -2);
		}

		// 手机号码是否已存在
		if($this->IsExistMobile())
		{
			$this->ajaxReturn(L('common_mobile_exist_error'), -3);
		}

		// 验证码公共基础参数
		$verify_param = array(
				'key_prefix' => 'reg',
				'expire_time' => MyC('common_verify_expire_time')
			);

		// 是否开启图片验证码
		if(MyC('home_user_reg_img_verify_state') == 1)
		{
			if(empty($_POST['verify']))
			{
				$this->ajaxReturn(L('common_param_error'), -10);
			}
			$verify = new \My\Verify($verify_param);
			if(!$verify->CheckExpire())
			{
				$this->ajaxReturn(L('common_verify_expire'), -11);
			}
			if(!$verify->CheckCorrect(I('verify')))
			{
				$this->ajaxReturn(L('common_verify_error'), -12);
			}
		}

		// 发送短信验证码
		$sms = new \My\Sms($verify_param);
		$code = GetNumberCode(6);
		if($sms->SendText(I('mobile'), MyC('home_sms_user_reg'), $code))
		{
			// 清除验证码
			if(isset($verify) && is_object($verify))
			{
				$verify->Remove();
			}

			$this->ajaxReturn(L('common_send_success'));
		} else {
			$this->ajaxReturn(L('common_send_error').'['.$sms->error.']', -100);
		}
	}

	/**
	 * [IsExistMobile 手机号码是否存在]
	 * @author   Devil
	 * @blog     http://gong.gg/
	 * @version  0.0.1
	 * @datetime 2017-03-08T10:27:14+0800
	 * @return   [boolean] [存在true, 不存在false]
	 */
	private function IsExistMobile()
	{
		$id = M('User')->where(array('mobile'=>I('mobile')))->getField('id');
		return !empty($id);
	}

	/**
	 * [Logout 退出]
	 * @author   Devil
	 * @blog     http://gong.gg/
	 * @version  0.0.1
	 * @datetime 2016-12-05T14:31:23+0800
	 */
	public function Logout()
	{
		session_destroy();
		redirect(__MY_URL__);
	}
}
?>