<?php
/**
 * 安装向导
 */
header('Content-type:text/html;charset=utf-8');
// 检测是否安装过
if (file_exists('./install.lock')) {
    echo '你已经安装过该系统，重新安装需要先删除./Public/install/install.lock 文件';
    die;
}
// 同意协议页面
if(@!isset($_GET['query']) || @$_GET['query']=='step1'){
    require './step1.html';
}
// 检测环境页面
if(@$_GET['query']=='step2'){
    require './step2.html';
}
// 创建数据库页面
if(@$_GET['query']=='step3'){
    require './step3.html';
}
// 安装成功页面
if(@$_GET['query']=='success'){
    // 判断是否为post
    if($_SERVER['REQUEST_METHOD']=='POST'){
        $data=$_POST;
        if (empty($data['email']) || empty($data['password'])) {
            die("<script>alert('邮箱或者密码不能为空.');history.go(-1)</script>");
        }
        // 连接数据库
        $link=@new mysqli("{$data['DB_HOST']}:{$data['DB_PORT']}",$data['DB_USER'],$data['DB_PWD']);
        // 获取错误信息
        $error=$link->connect_error;
        if (!is_null($error)) {
            // 转义防止和alert中的引号冲突
            $error=addslashes($error);
            die("<script>alert('数据库链接失败:$error');history.go(-1)</script>");
        }
        // 设置字符集
        $link->query("SET NAMES 'utf8'");
        $link->server_info>5.0 or die("<script>alert('请将您的mysql升级到5.0以上');history.go(-1)</script>");
        // 创建数据库并选中
        if(!$link->select_db($data['DB_NAME'])){
            $create_sql='CREATE DATABASE IF NOT EXISTS '.$data['DB_NAME'].' DEFAULT CHARACTER SET utf8;';
            $link->query($create_sql) or die('创建数据库失败');
            $link->select_db($data['DB_NAME']);
        }
        // 导入sql数据并创建表
        $mysql_str=file_get_contents('./mysql.sql');
        $sql_array=preg_split("/;[\r\n]+/", str_replace('hh_',$data['DB_PREFIX'],$mysql_str));
        foreach ($sql_array as $k => $v) {
            if (!empty($v)) {
                $link->query($v);
            }
        }
      
        $name='admin';
        $email=$data['email'];
        $salt='haiua';
        $password=md5(md5($data['password'].$salt));
        $is_active=0;
        $remark='超级管理员';
        $current_login_ip= $_SERVER['REMOTE_ADDR'];
        $current_login_time=time();
        $prev_login_ip=$_SERVER['REMOTE_ADDR'];
        $prev_login_time=time();
        $m_count=1;
        $session=0;
        $inster_sql="INSERT INTO `{$data['DB_PREFIX']}user` VALUES(1,'{$name}','{$email}','{$password}',$is_active,'{$remark}','{$current_login_ip}', '{$current_login_time}','{$prev_login_ip}', '{$prev_login_time}', $m_count, $session)";

       $res = mysqli_query($link,$inster_sql);
        if (!$res) {
            echo "Failed to run query: (" . $link->errno . ") " . $link->error;
        }
        $link->close();
        $db_str=<<<php
<?php
return array(

//*************************************数据库设置*************************************
    'DB_TYPE'               =>  'mysql',                 // 数据库类型
    'DB_HOST'               =>  '{$data['DB_HOST']}',     // 服务器地址
    'DB_NAME'               =>  '{$data['DB_NAME']}',     // 数据库名
    'DB_USER'               =>  '{$data['DB_USER']}',     // 用户名
    'DB_PWD'                =>  '{$data['DB_PWD']}',      // 密码
    'DB_PORT'               =>  '{$data['DB_PORT']}',     // 端口
    'DB_PREFIX'             =>  '{$data['DB_PREFIX']}',   // 数据库表前缀
    'RBAC_DB_DSN'  =>   array(
        'DB_TYPE'       =>  'mysql',      
        'DB_HOST'       =>  '{$data['DB_HOST']}',  
        'DB_NAME'       =>  '{$data['DB_NAME']}',           
        'DB_USER'       =>  '{$data['DB_USER']}',      
        'DB_PWD'        =>  '{$data['DB_PWD']}',     
        'DB_PORT'       =>  '{$data['DB_PORT']}',       
        'DB_PREFIX'     =>  '{$data['DB_PREFIX']}',  
    ),    //数据库连接DSN
    
    'RBAC_ROLE_TABLE'=>'{$data['DB_PREFIX']}role' ,     //角色表名称
    'RBAC_USER_TABLE' =>'{$data['DB_PREFIX']}role_user',     //用户表名称
    'RBAC_ACCESS_TABLE'=>'{$data['DB_PREFIX']}access' ,       //权限表名称
    'RBAC_NODE_TABLE' => '{$data['DB_PREFIX']}node' ,   //节点表名称
);
php;
        // 创建数据库链接配置文件
        file_put_contents('../../App/Common/Conf/database.php', $db_str);
        @touch('./install.lock');
        require './success.html';
    }

}

