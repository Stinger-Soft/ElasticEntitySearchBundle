<?php
declare(strict_types=1);

class TestLogger extends \Psr\Log\AbstractLogger {

	public function log($level, $message, array $context = array()) {
		if(!in_array($level, ['error', 'warning', 'critical'], true)) {
//			return;
		}
		echo "================\r\n".$level.': '.$message."\r\n".print_r($context, true)."\r\n================\r\n";
	}
}
