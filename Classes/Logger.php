<?php

class Logger {

	public function log(mixed $message = '', bool $isReturnCarriage = TRUE): void {
		$returnCarriage = $isReturnCarriage === TRUE ? chr(10) : '';
		if (is_array($message)) {
			foreach ($message as $line) {
				print $line . $returnCarriage ;
			}
		} else {
			print $message . $returnCarriage;
		}
	}

}
