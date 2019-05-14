<?php
// hideMyOauth - скрипт для работы сервиса https://imgAdmin.forAlice.ru с безопасным 
// использованием конфиденциального идентификатора OAuth пользователя.
// Антон Г. Федерольф (zz-anton@yandex.ru)
// Релиз от: 2019-05-14
//
//
//
// Установка:
// - укажите Ваш OAuth в строках ниже;
// - разместите php-скрипт на доверенной площадке и опубликуйте сервис в интернете.
//   * В текущей версии, необходимо чтобы у скрипта были права на создание папок и 
//     файлов в его директории. Это необходимо для работы с файлами добавляемыми в 
//     Яндекс.Диалоги. В последующих версиях данная необходимость будет устранена;
// - укажите URL опубликованного скрипта в сервисе https://imgAdmin.forAlice.ru в 
//   поле OAuth.
//
//
//
// НАСТРОЙКА
//
// Укажите Ваш OAuth здесь
$OAUTH = 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx';
//
// Следующий параметр необходим для ведения единой библиотеки изображений ImgAdmin
// при использовании различных методов авторизации (через OAuth или этот скрипт).
// Если Вы не планируете в будущем авторизоваться в ImgAdmin по OAuth можете 
// выставить следующий параметр в null.
$HIDDEN_OAUTH = md5( $OAUTH );
//$HIDDEN_OAUTH = null;



//ini_set('xdebug.var_display_max_depth', '10');
//ini_set('xdebug.var_display_max_children', '256');
//ini_set('xdebug.var_display_max_data', '1024');



// common
//
/**
 * пользовательская функция для обработки ошибок
 */
function userErrorHandler( $errno, $errmsg, $filename, $linenum ){
	$errortype = array (
				E_ERROR              => 'E_ERROR',
				E_WARNING            => 'E_WARNING',
				E_PARSE              => 'E_PARSE',
				E_NOTICE             => 'E_NOTICE',
				E_CORE_ERROR         => 'E_CORE_ERROR',
				E_CORE_WARNING       => 'E_CORE_WARNING',
				E_COMPILE_ERROR      => 'E_COMPILE_ERROR',
				E_COMPILE_WARNING    => 'E_COMPILE_WARNING',
				E_USER_ERROR         => 'E_USER_ERROR',
				E_USER_WARNING       => 'E_USER_WARNING',
				E_USER_NOTICE        => 'E_USER_NOTICE',
				E_STRICT             => 'E_STRICT',
				E_RECOVERABLE_ERROR  => 'E_RECOVERABLE_ERROR'
				);
	$err = (isset($errortype[$errno])?$errortype[$errno]:$errno).(!empty($errmsg)?('; '.$errmsg):'').(!empty($linenum)?('; at line: '.$linenum):'');
	
	error_log( $err, 0 );
	
	// прерываем работу только по ошибке
	if ( in_array( $errno, array( E_ERROR, E_USER_ERROR ) ) || empty( $_REQUEST ) ){
		exitWithAnswer( $err );
	}
}
error_reporting( E_ALL );
set_error_handler( 'userErrorHandler' );

/**
 * Возвращает вычищенное значение входного параметра
 */
function prepareRequestParam( $param ){
	$result = $param;
	
	if ( gettype( $param ) === 'string' ) {
		$result = strip_tags( $param );
	} else 
	if ( gettype( $param ) === 'array' ) {
		$result = null;
		foreach( $param as $key => $value ){
			$result[ strip_tags( $key ) ] = strip_tags( $value );
		}
	} 
	
	return $result;
}
/**
 * Возвращает значение массива
 */
function getValueFromArrayByKey( $arr, $key, $def ){
	if ( empty( $arr ) ) return $def;
	if ( !isset( $arr[ $key ] ) ) return $def;
	if ( $arr[ $key ] === null ) return $def;
	
	return $arr[ $key ];
}
/**
 * Возвращает результат выполнения (JSON)
 */
function exitWithJsonAnswer( $inData ){
	exit( json_encode( $inData ) );
}
/**
 * Возвращает результат выполнения
 */
function exitWithAnswer( $inData ){
	exit( $inData );
}


// body
//
if ( !isset( $OAUTH ) || empty( $OAUTH ) ) exitWithAnswer( 'OAuth не определён. Укажите OAuth в первых строках этого файла (блок НАСТРОЙКА)' );


$request = prepareRequestParam( $_POST );
if ( empty( $request ) ) exitWithAnswer( 'Готов к работе' );

$action  = getValueFromArrayByKey( $request, 'action' , null );
if ( empty( $action ) ) exitWithAnswer( 'Неверные параметры' );

if ( $action === 'getHiddenOauth' ){
	$result = (Object) array();
	$result->result = true;
	if ( !empty( $HIDDEN_OAUTH ) ) $result->ho20190405 = $HIDDEN_OAUTH;
	exitWithJsonAnswer( $result );
} else
if ( $action === 'checkQuota' ){
	try{
		$ch = curl_init();
		curl_setopt( $ch, CURLOPT_URL           , 'https://dialogs.yandex.net/api/v1/status' );
		curl_setopt( $ch, CURLOPT_TIMEOUT       , 30 );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
		curl_setopt( $ch, CURLOPT_HTTPHEADER    , array( "Authorization: OAuth $OAUTH" ) );
		
		$response = curl_exec( $ch );
		curl_close( $ch );
		sleep(1);
		
		if ( $response === false ) throw new Exception( 'Не удалось получить ответ от сервера Яндекс.Диалоги', E_ERROR );
		if ( empty( $response ) ) throw new Exception( 'Ответ сервера Яндекс.Диалоги пуст', E_ERROR );
		
		$responseDecoded = json_decode( $response );
		if ( $responseDecoded === null ) throw new Exception( 'Непредвиденный ответ сервера Яндекс.Диалоги: '.$response, E_ERROR );
		
		if ( isset( $responseDecoded->message ) ) throw new Exception( 'Ответ сервера Яндекс.Диалоги: '.$responseDecoded->message.'', E_ERROR );
		
		//TODO - проверить формат полученных данных
		
		exitWithJsonAnswer( $responseDecoded );
	} catch ( Exception $e ) {
		exitWithAnswer( $e->getMessage() );
	}
} else if ( $action === 'getImagesList' ){
	$skillId  = getValueFromArrayByKey( $request, 'skillId' , null );
	if ( empty( $skillId ) ) exitWithAnswer( 'Неверные параметры (skillId)' );
	
	try{
		$ch = curl_init();
		curl_setopt( $ch, CURLOPT_URL           , "https://dialogs.yandex.net/api/v1/skills/$skillId/images" );
		curl_setopt( $ch, CURLOPT_TIMEOUT       , 30 );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
		curl_setopt( $ch, CURLOPT_HTTPHEADER    , array( "Authorization: OAuth $OAUTH" ) );
		
		$response = curl_exec( $ch );
		curl_close( $ch );
		
		if ( $response === false ) throw new Exception( 'Не удалось получить ответ от сервера Яндекс.Диалоги', E_ERROR );
		if ( empty( $response ) ) throw new Exception( 'Ответ сервера Яндекс.Диалоги пуст', E_ERROR );
		
		$responseDecoded = json_decode( $response );
		if ( $responseDecoded === null ) throw new Exception( 'Непредвиденный ответ сервера Яндекс.Диалоги: '.$response, E_ERROR );
		
		if ( isset( $responseDecoded->message ) ) throw new Exception( 'Ответ сервера Яндекс.Диалоги: '.$responseDecoded->message.'', E_ERROR );
		
		exitWithJsonAnswer( $responseDecoded );
	} catch ( Exception $e ) {
		exitWithAnswer( $e->getMessage() );
	}
} else if ( $action === 'addImageFromUrl' ){
	$skillId = getValueFromArrayByKey( $request, 'skillId' , null );
	if ( empty( $skillId ) ) exitWithAnswer( 'Неверные параметры (skillId)' );
	
	$fileUrl = getValueFromArrayByKey( $request, 'fileUrl' , null );
	if ( empty( $fileUrl ) ) exitWithAnswer( 'Неверные параметры (fileUrl)' );
	
	try{
		$requestData = array( "url" => $fileUrl );
		$requestDataString = json_encode( $requestData );
		
		$ch = curl_init();
		curl_setopt( $ch, CURLOPT_URL           , "https://dialogs.yandex.net/api/v1/skills/$skillId/images" );
		curl_setopt( $ch, CURLOPT_TIMEOUT       , 30 );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
		curl_setopt( $ch, CURLOPT_HTTPHEADER    , array( "Authorization: OAuth $OAUTH", 'Content-Type: application/json' ) );
		curl_setopt( $ch, CURLOPT_POST          , 1 );
		curl_setopt( $ch, CURLOPT_POSTFIELDS    , $requestDataString );
		
		$response = curl_exec( $ch );
		curl_close( $ch );
		
		if ( $response === false ) throw new Exception( 'Не удалось получить ответ от сервера Яндекс.Диалоги', E_ERROR );
		if ( empty( $response ) ) throw new Exception( 'Ответ сервера Яндекс.Диалоги пуст', E_ERROR );
		
		$responseDecoded = json_decode( $response );
		if ( $responseDecoded === null ) throw new Exception( 'Непредвиденный ответ сервера Яндекс.Диалоги: '.$response, E_ERROR );
		
		if ( isset( $responseDecoded->message ) ) throw new Exception( 'Ответ сервера Яндекс.Диалоги: '.$responseDecoded->message.'', E_ERROR );
		
		exitWithJsonAnswer( $responseDecoded );
	} catch ( Exception $e ) {
		exitWithAnswer( $e->getMessage() );
	}
} else if ( $action === 'addImageFromFile' ){
	$skillId = getValueFromArrayByKey( $request, 'skillId' , null );
	if ( empty( $skillId ) ) exitWithAnswer( 'Неверные параметры (skillId)' );
	
	$fileContent = getValueFromArrayByKey( $request, 'fileContent' , null );
	if ( empty( $fileContent ) ) exitWithAnswer( 'Неверные параметры (fileContent)' );
	$fileContent = base64_decode( $fileContent );
	
	try{
		//TODO2 - убрать использование файлов
		$basePath = $_SERVER[ "DOCUMENT_ROOT" ] . DIRECTORY_SEPARATOR . '_data' . DIRECTORY_SEPARATOR . 'tmpUpload' . DIRECTORY_SEPARATOR;
		if ( !is_dir( $basePath ) ) mkdir( $basePath, 0777, true );
		$filePath = $basePath . uniqid( 'tmpFile_' );
		file_put_contents( $filePath, $fileContent );
		
		if ( !file_exists( $filePath ) ) throw new Exception( 'Файл не найден.', E_ERROR );
		
		$post[ 'file' ] = new CurlFile( $filePath, mime_content_type( $filePath ) );
		
		$ch = curl_init();
		curl_setopt( $ch, CURLOPT_URL           , "https://dialogs.yandex.net/api/v1/skills/$skillId/images" );
		curl_setopt( $ch, CURLOPT_TIMEOUT       , 30 );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
		curl_setopt( $ch, CURLOPT_HTTPHEADER    , array( "Authorization: OAuth $OAUTH", "Content-Type: multipart/form-data" ) );
		curl_setopt( $ch, CURLOPT_POST          , 1 );
		curl_setopt( $ch, CURLOPT_POSTFIELDS    , $post );
		
		$response = curl_exec( $ch );
		curl_close( $ch );
		
		if ( $response === false ) throw new Exception( 'Не удалось получить ответ от сервера Яндекс.Диалоги', E_ERROR );
		if ( empty( $response ) ) throw new Exception( 'Ответ сервера Яндекс.Диалоги пуст', E_ERROR );
		
		$responseDecoded = json_decode( $response );
		if ( $responseDecoded === null ) throw new Exception( 'Непредвиденный ответ сервера Яндекс.Диалоги: '.$response, E_ERROR );
		
		if ( isset( $responseDecoded->message ) ) throw new Exception( 'Ответ сервера Яндекс.Диалоги: '.$responseDecoded->message.'', E_ERROR );
		
		//TODO2 - убрать использование файлов
		@unlink( $filePath );
		
		exitWithJsonAnswer( $responseDecoded );
	} catch ( Exception $e ) {
		//TODO2 - убрать использование файлов
		@unlink( $filePath );
		
		exitWithAnswer( $e->getMessage() );
	}
} else
if ( $action === 'yandexDeleteImage' ){
	$skillId  = getValueFromArrayByKey( $request, 'skillId' , null );
	if ( empty( $skillId ) ) exitWithAnswer( 'Неверные параметры (skillId)' );
	
	$imageId  = getValueFromArrayByKey( $request, 'imageId' , null );
	if ( empty( $imageId ) ) exitWithAnswer( 'Неверные параметры (imageId)' );
	
	try{
		$ch = curl_init();
		curl_setopt( $ch, CURLOPT_URL           , "https://dialogs.yandex.net/api/v1/skills/$skillId/images/$imageId" );
		curl_setopt( $ch, CURLOPT_TIMEOUT       , 30 );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
		curl_setopt( $ch, CURLOPT_HTTPHEADER    , array( "Authorization: OAuth $OAUTH" ) );
		curl_setopt( $ch, CURLOPT_CUSTOMREQUEST , "DELETE" );
		
		$response = curl_exec( $ch );
		curl_close( $ch );
		
		if ( $response === false ) throw new Exception( 'Не удалось получить ответ от сервера Яндекс.Диалоги', E_ERROR );
		if ( empty( $response ) ) throw new Exception( 'Ответ сервера Яндекс.Диалоги пуст', E_ERROR );
		
		$responseDecoded = json_decode( $response );
		if ( $responseDecoded === null ) throw new Exception( 'Непредвиденный ответ сервера Яндекс.Диалоги: '.$response, E_ERROR );
		
		if ( isset( $responseDecoded->message ) && $responseDecoded->message === 'Image not found' ) exitWithJsonAnswer( $responseDecoded );
		
		if ( isset( $responseDecoded->message ) ) throw new Exception( 'Ответ сервера Яндекс.Диалоги: '.$responseDecoded->message.'', E_ERROR );
		
		exitWithJsonAnswer( $responseDecoded );
	} catch ( Exception $e ) {
		exitWithAnswer( $e->getMessage() );
	}
} else {
	exitWithAnswer( 'Неизвестное действие' );
}




