<?php
require_once('ipage.php');
class usersController extends ipage {
	
	public function initialize(){
		parent::initialize();
		$this->users=new \kelimeci\users();

		$this->addLib('mailHandler');
	}

	public function register(){
		
		$r=$this->r;

		if(!isset($r['email']) && !isset($r['username']) 
			&& !isset($r['password']))
			return 'Kullanıcı bilgileri eksik ya da hatalı!';
		
		$err=$this->checkUsername($r['username']);
		if($err!==true)
			return $err;
		
		$err=$this->checkEmail($r['email']);
		if($err!==true)
			return $err;
		
		$c=$this->users->register(
			$r['email'],
			$r['username'],
			$r['password']
		);

		// If register okay
		if($c>0){
			// Login for starting the session
			$this->login($r['username'],$r['password']);
			return 1;
		}
		return 0;
	}
	
	public function checkUserName($username=null){
		$r=$this->r;
		if($username!=null)
			$r['username']=$username;
		
		if (isset($r['username'])){
			if ($this->users->checkUserInfo(
					'username',
					$r['username'])
				)
				return 'Kullanıcı adı kullanılıyor. Lütfen değiştiriniz.';
			else 
				return true;
		}
	}
	
	public function checkEmail($email=null){
		$r=$this->r;
		if($email!=null)
			$r['email']=$email;
		
		if (isset($r['email'])){
			if ($this->users->checkUserInfo('email',$r['email']))
				return 'E-posta adresi kullanılıyor. Lütfen değiştiriniz.';
			else 
				return true;
		}
	}
	
	public function viewProfile(){
		if (!$this->isLogined) return false;
		
		$user=$this->users->getUserInfo($this->u->id);
		
		return $this->loadView(
			'profile.php',
			$user,
			false
		);

	}
	
	public function update(){
		$r=$this->r;
		$type=$r['type'];
		$uId=$this->u->id;
		if(isset($type) && !empty($type)){

			$rtn=true;

			if($type=='personelInfo' && isset($r['fname']) 
				&& isset($r['lname']) && isset($r['birthDate'])){
					
				$rtn=$this->users->updateUserInfo($uId,'fname',$r['fname']);
				if($rtn!==true)
					return 'Ad güncellenemedi!';
					
				$rtn=$this->users->updateUserInfo($uId,'lname',$r['lname']);
				if($rtn!==true)
					return 'Soyad güncellenemedi!';

				$rtn=$this->users->updateUserInfo($uId,'birthDate',$r['birthDate']);
				if($rtn!==true)
					return 'Doğum tarihi güncellenemedi!';

			}
			else if($type=='email' && isset($r['email'])){

				$uInfo=$this->users->getUserInfo($uId);

				// Eğer mevcut e-posta adresi ise
				if($uInfo->email==$r['email'])
					return '1';

				// Yeni e-posta adresinin kullanılabilirliğini kontrol et
				$rtn=$this->checkEmail($r['email']);
				if($rtn!==true)
					return $rtn;

				$rtn=$this->users->updateUserInfo($uId,'email',$r['email']);
				if($rtn!==true)
					return 'E-posta adresi güncellenemedi!';

			}
			else if($type=='password' && isset($r['currentPassword'])
				&& isset($r['newPassword'])){
				
				if(empty($r['currentPassword']) || empty($r['newPassword']))
					return 'Mevcut şifreyi ve yeni şifreyi girmelisin!';

				$uInfo=$this->users->getUserInfo($uId);
				// Mevcut şifreyi kontrol et
				if(!$this->users->validateLogin($uInfo->username,$r['currentPassword']))
					return 'Mevcut şifre hatalı!';

				$rtn=$this->users->updateUserInfo($uId,'password',$r['newPassword'],true);
				if($rtn!==true)
					return 'Şifre güncellenemedi!';

			}
			else if($type=='practice' && isset($r['practice']) && isset($r['city'])){
					
				$rtn=$this->users->updateUserInfo($uId,'practice',$r['practice']);
				if($rtn!==true)
					return 'Pratik yapma bilgisi güncellenemedi!';
					
				$rtn=$this->users->updateUserInfo($uId,'city',$r['city']);
				if($rtn!==true)
					return 'Şehir güncellenemedi!';

			}

			/**
			 * the session and user object is updating.
			 * */
			$this->u=$this->users->getUserInfo($uId);
			$this->session->create($this->u);

			return '1';
			
		}
		else return 'Güncelleme işlemi yapılamadı!';
	}	
	
	public function login(){
		$r=$this->r;
		if (isset($r['username']) && isset($r['password'])){
			$r=$this->users->validateLogin($r['username'],$r['password']);
			if ($r!==false){
				$this->u=$r;
				$this->session->create($r);

				setcookie(
					session_name(),session_id(),
					time()+3600*24*150 // oturum ömrü 150 gün olarak belirleniyor
				);

				return true;
			}
			else
				return 'Giriş başarışız!';
		}
		
		return 'Kullanıcı bilgileri eksik yada hatalı!';
	}

	public function logout(){
		$this->session->kill();
		header('location:/');
	}

	public function feedBack(){

		$r=$this->r;

		if(isset($r['comments']) && !empty($r['comments'])){
			$rtn=$this->users->feedBack($r['email'],$r['comments']);
			
			// If the feedback's inserted into the db, send email
			if($rtn===true){

				// Emails to inform for coming new feedback
				$emails=array(
					'muatik@gmail.com',
					'mr.ermangulhan@gmail.com',
					'alpaycom@gmail.com'
				);
				
				// Configure email
				$mHandler=new mailHandler();
				$mHandler->antiflood=false;
				$mHandler->content_type='text/html; charset=utf-8';
				$mHandler->from='bilgi@kelimeci.net';
				$mHandler->to=$emails;
				$mHandler->subject='Kelimeci - Yeni Geribildirim Var';
				$mHandler->message=
				'
					<table style="border:none;">
						<tr><th>E-posta:</th><td>'.$r['email'].'</td></tr>
						<tr><td colspan="2">&nbsp;</td></tr>
						<tr><th>Görüş:</th><td>'.$r['comments'].'</td></tr>
					</table>
				';

				// Send the email(s)
				$rtn=$mHandler->send();

				//if($rtn!==true) echo $mHandler->error;

				return '1';
			}
			else
				return 'Görüşünüz kayıt edilemedi!';
		}

		return 'Görüşünüzü yazın!';

	}
}
?>
