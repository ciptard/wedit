<?php

include_once('json_lib.php');
require_once('settings.php');
require_once('lang.php');

$html = file_get_contents('html.html');
//Загрузка языков
$js_lang = '';
foreach ($lang[$settings->language] as $phrase => $translate) {
  $js_lang .= $js_lang ? ', ' : '';
  $js_lang .= '{phrase: "'.$phrase.'", translate: "'.$translate.'"}';
}
$js_lang = 'var lang = ['.$js_lang.'];';
$html = str_replace('[#lang#]', $js_lang, $html);

//Авторизация
if ($settings->logintype != '') {
  $info = null;
  if (strtolower($settings->logintype) == 'iris') {
    include_once($settings->root_path.'/core/engine/applib.php');
    include_once($settings->root_path.'/core/engine/auth.php');
    if (!session_id()) {
      @session_start();
      if (!session_id()) {
        $info = t('Can\'t open the settion').'!';
      }
    }
    $path = $_SESSION['INDEX_PATH'];

    if (!$info && !isAuthorised()) {
      $info = t('Don\'t authorised in Iris CRM').' '.
        '<a href="'.$settings->root_path.'">'.t('Authorise').'</a>.';
    }
    if (!$info && !IsUserInAdminGroup()) {
      $info = t('You must be authorised like admin').' '.
        '<a href="'.$settings->root_path.'">'.t('Authorise').'</a>.';
    }
  }
  else {
    $info = t('Unknown login type').': '.$settings->logintype;
  }
  if ($info) {
    $html_msg = file_get_contents('info.html');
    $html = str_replace('[#body#]', $html_msg, $html);
    $html = str_replace('[#header#]', t('System message'), $html);
    $html = str_replace('[#info#]', '<p>'.t($info).'</p>', $html);
    echo $html;
    return;
  }
}
$filename_encoding = $settings->filename_encoding;


//Определяем путь
$root = str_replace('\\', '/', realpath($settings->root_path));
$wedit = str_replace('\\', '/', realpath('.'));
$p_path = str_replace('\\', '/', $_GET['path']);
$p_path = filename_encode($p_path, $filename_encoding);

//TODO: "/" в зависимости от ОС
$current = str_replace('\\', '/', realpath($root.'/'.$p_path));
if (strlen($current) < strlen($root)) {
  $current = $root;
}
$current_rel = str_replace($root, '', $current);

//Путь к текущему каталогу
$path = '';
$path_simple = '';
$path_array = explode('/', $current_rel);
foreach ($path_array as $val) {
  $val = filename_decode($val, $filename_encoding);
  $path_simple .= ($path_simple != '/' ? '/' : '').$val;
  $path .= ($path ? '/' : '<a href="?path=.">&hellip;</a>').'<a href="?path='.$path_simple.'">'.$val.'</a>';
}



//Если надо выполнить какие-то команды над файлами
//TODO: коды ошибок
$info = '';
switch ($_POST['operation']) {

  //Создание файла
  case 'create':
    $new_filename = $current.'/'.$_POST['newname'];
    if (!file_exists($new_filename)) {
      if ($_POST['newtype'] == 'Directory') {
        mkdir($new_filename);
      }
      else {
        touch($new_filename);
      }
    }
    break;

  //Изменение файла (переименование)
  case 'change':
    $old_filename = $current.'/'.$_POST['oldname'];
    $change_filename = $current.'/'.$_POST['changename'];
    if (file_exists($old_filename)) {
      rename($old_filename, $change_filename);
      //chmod($change_filename, $_POST['changeaccess']);
    }
    break;

  //Удаление файлов
  case 'delete':
    $json = new Services_JSON();
    $filenames = $json->decode($_POST['filelist']);
    foreach($filenames as $name) {
      $filename = $current.'/'.$name;
      if (file_exists($filename)) {
        unlink($filename);
      }
    }
    break;

  //Загрузка файлов
  case 'upload':
    if (isset($_FILES)) {
      $loaded = 0;
      foreach($_FILES['file']['name'] as $i => $name) {

        $filename = $current.'/'.$name;
        if (file_exists($filename)) {
          $info .= '<li>'.$filename.' ('.t('File already exists').')</li>';
          continue;
        }
        else {
          if (!move_uploaded_file($_FILES['file']['tmp_name'][$i], $filename)) {
            $info .= '<li>'.$filename.' ('.t('Copy error').')</li>';
            continue;
          }
        }
        $loaded++;
      }
      $info = $info ? '<h3>Не удалось загрузить следующие файлы</h3><ul>'.$info.'</ul>' : $info;
      $info .= '<h3>'.t('Files loaded').': '.$loaded.' '.t('from').' '.count($_FILES['file']['name']).'</h3><hr>';
    }
    else {
      $info .= '<h3>'.t('Files not selected for upload').'</h3>';
    }
    break;
}


//Если это каталог, то отобразим содержимое
if (!is_file($current)) {

  //Список файлов
  $list = '<table id="files"><thead><tr>'.
    '<th></th>'.
    '<th>'.t('Name').'</th>'.
    '<th>'.t('Type').'</th>'.
    '<th>'.t('Access').'</th>'.
//    '<th>'.t('Ext').'</th>'.
    '<th>'.t('Size').'</th>'.
    '<th>'.t('Change date').'</th>'.
    '<th></th>'.
    '</tr></thead>'.
    '<tbody>[#tbody#]</tbody></table>';

  $rows = '';
  $file_list = read_from_directory($current);
  foreach ($file_list as $item) {
    $link = $current_rel.'/'.$item['name'];
    $link_abs = str_replace('\\', '/', realpath($root.'/'.$link));
    if (strlen($link_abs) < strlen($root)) {
      $link_abs = $root;
    }
    $link_rel = str_replace($root, '', $link_abs);
    if (($p_path != '.' || $link_rel) && $link_rel != $p_path) {
      $link_rel = $link_rel ? '?path='.$link_rel : '?path=.';

      $item['name'] = filename_decode($item['name'], $filename_encoding);
      $link_rel = filename_decode($link_rel, $filename_encoding);
      
      $rows .= '<tr>'.
        '<td><input type="checkbox" id="f_'.$item['name'].'" name="'.$item['name'].'" value="'.$item['name'].'"></td>'.
        '<td><a href="'.$link_rel.'">'.$item['name'].'</a></td>'.
        '<td>'.($item['is_dir'] ? t('Directory'): '').'</td>'.
        '<td>'.$item['access'].'</td>'.
//        '<td>'.$item['ext'].'</td>'.
        '<td>'.$item['size'].'</td>'.
        '<td>'.$item['modifydate'].'</td>'.
        '<td><a href="'.$link_rel.'&action=download">'.t('Download').'</a></td>'.
        '</tr>';
    }
  }
  $list = str_replace('[#tbody#]', $rows, $list);


  $html_list = file_get_contents('list.html');
  $html = str_replace('[#body#]', $html_list, $html);
  $html = str_replace('[#info#]', $info, $html);
  $html = str_replace('[#list#]', $list, $html);
  $html = str_replace('[#Upload#]', t('Upload'), $html);
  $html = str_replace('[#Download#]', t('Download'), $html);
  $html = str_replace('[#Create#]', t('Create'), $html);
  $html = str_replace('[#Change#]', t('Change'), $html);
  $html = str_replace('[#Delete#]', t('Delete'), $html);
  $html = str_replace('[#Rename#]', t('Rename'), $html);
}
else {
  //Если в $current - файл и его надо скачать, то отдаём его на скачивание
  if ($_GET['action'] == 'download') {
    //header('Content-Type: application/octet-stream');
    //header('Location: '.$current);
    $html .= 'TODO';
  }
  else {  
    //Если в $current - файл, то открывать его на редактирование
    $contents = '<textarea></textarea>';

    $html_edit = file_get_contents('edit.html');
    $html = str_replace('[#body#]', $html_edit, $html);
    $html = str_replace('[#filename#]', $current, $html);
    $html = str_replace('[#status#]', t('Loading').'...', $html);
    $html = str_replace('[#Save#]', t('Save'), $html);
    $html = str_replace('[#Reload#]', t('Reload'), $html);

    $html = str_replace('[#savestatus#]', t('Saving').'&hellip;', $html);
  }
}

$html = str_replace('[#path#]', $path, $html);

echo $html;


//Чтение содержимого каталога в массив и сортировка
function read_from_directory($directory, $sort_column = null, $sort_order = null)
{
  $array = null;
  //Файлы и каталоги ведём отдельно, чтобы группировать их при сортировке
  $array_dir = array();
  $array_files = array();
  $dir = scandir($directory);
  for ($i=0; $i<count($dir); $i++) {
    $item_abs = $directory.'/'.$dir[$i];
    $is_dir = is_dir($item_abs);
    
    if ($is_dir) {
      $temp =& $array_dir[];
    }
    else {
      $temp =& $array_files[];
    }

    $temp['name'] = $dir[$i];
    $temp['is_dir'] = $is_dir; //0 - файл, 1 - каталог
    $temp['size'] = !$is_dir ? filesize($item_abs) : ''; //размер в байтах
    $temp['access'] = (is_readable($item_abs) ? 'r' : '-').
      (is_writable($item_abs) ? 'w' : '-').
      (is_executable($item_abs) ? 'x' : '-');
//    $temp['ext'] = (is_link($item_abs) ? 'l' : '').
//      (is_uploaded_file($item_abs) ? 'u' : '');
    $temp['modifydate'] = date("d.m.Y H:i:s", fileatime($item_abs)); //дата изменения
  }
  
  //TODO: Сортируем
  
  //Объединяем список каталогов и файлов
  $array = array_merge($array_dir, $array_files);
  return $array;
}


//Кодировка имени файла в кодировку файловой системы
function filename_encode($name, $encoding)
{
  return $encoding ? iconv('utf-8', $filename_encoding, $name) : $name;
}


//Декодировка имени файла в utf-8
function filename_decode($name, $encoding)
{
  return $encoding ? iconv($filename_encoding, 'utf-8', $name) : $name;
}

?>