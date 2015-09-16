#WAFPHP#
A php framework for Web Application Firewall.

一个PHP级Web应用防护框架。

旨在提供一个与现有代码互不冲突干扰的PHP级Web应用防护框架，可基于此框架之上开发各种诸如防机器人恶意采集等Web应用防护插件，即插即用，乃居家旅行必备良药。

PS:当然，这只是一种思路，适用于某些特殊场景，它并不能完全替代你的专业防火墙 :)

起步：

	// 为避免影响WAFPHP的输出，在加载WAFPHP之前请勿有任何html输出
	require_once '#your WAFPHP path#/WAFPHP.php';
	// 单例模式启动WAFPHP
	$wafPHP = WAFPHP\WAFPHP::getInstance();
	// 执行脚本检测
	$wafPHP->runCheck();
	#Your code#
或者

	// 为避免影响WAFPHP的输出，在加载WAFPHP之前请勿有任何html输出
	require_once '#your WAFPHP path#/WAFPHP.php';
	// 可根据需求在调用时使用独立配置，默认使用配置文件中的配置
	$config = WAFPHP\WAFPHP::getCurrentConfig();
	// 修改特定配置参数
	$config['SOME_CONFIG'] = 'Your value';
	// 以自定义配置启动WAFPHP
	$wafPHP = WAFPHP\WAFPHP::getInstance($config);
	// 执行脚本检测
	$wafPHP->runCheck();
	#Your code#


###配置###
#####配置文件路径：#####
\#your WAFPHP path\#/Conf/config.default.php

详细配置请参考配置文件中的备注

github：
[here](https://github.com/ZoaChou/WAFPHP)

进阶版教程：
[here](https://www.mudoom.com/Article/show/id/35.html)

高阶版教程：
[here](https://www.mudoom.com/Article/show/id/36.html)