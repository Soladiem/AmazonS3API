<?php

// Include the SDK using the Composer autoloader
require 'vendor/autoload.php';

use Aws\S3\S3Client;
use Aws\S3\MultipartUploader;
use Aws\Exception\MultipartUploadException;

// Callback URL
$redirectUri = 'http://' . $_SERVER ['HTTP_HOST'];
$bucket = 'drive-19012018';

$s3Client = new S3Client([
    'version'     => 'latest',
    'region'      => 'us-west-2',
    'credentials' => [
        'key'    => 'AKIAJT4HQHXQRNDFC6KQ',
        'secret' => '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',
    ],
]);

if ($_POST){

    // Upload
    if (isset($_POST['upload']) && isset($_FILES['files'])){
        if ($_FILES['files']['error'][0] == 0) {
            $files = normalizeFilesArray($_FILES);

            foreach ($files as $file) {
                $uploader = new MultipartUploader($s3Client, $file['tmp_name'], [
                    'bucket' => $bucket,
                    'key'    => $file['name'],
                    'acl'    => 'public-read'
                ]);

                try {
                    $uploader->upload();
                } catch (MultipartUploadException $e) {
                    die($e->getMessage() . "<br>");
                }
            }
            location($redirectUri);
        }
    }

    // Delete
    if (isset($_POST['delete']) && isset($_POST['remfile'])){
        $result = $s3Client->deleteObject([
            'Bucket' => $bucket,
            'Key' => $_POST['remfile'],
        ]);
        location($redirectUri);
    }

}


/*
 * Формируем вывод объектов из Амазона
 */
$listObjects = $s3Client->listObjects([
    'Bucket' => $bucket
])->toArray();

$listFiles = array();
$i=1;

if (!empty($listObjects['Contents'])) {
    foreach ($listObjects['Contents'] as $k => $f) {
        $listFiles[$k] = $f;
        $listFiles[$k]['position'] = $i++;
        $listFiles[$k]['folder'] = isFolder($f['Key']);

        // Сортировка для папок и вложенных файлов
        if (stripos($f['Key'], '/') !== false) {
            $listFiles[$k]['position'] = 0;
        }
    }
}


// Функция сортировки
usort($listFiles, function($a, $b) {
    return $a['position'] <=> $b['position'];
});

// Является ли папкой
function isFolder($name)
{
    $e = explode('/', $name);
    if (isset($e[1]) && empty($e[1])){
        return 1;
    }
    return 0;
}

// Переадресация по url
function location($url) {
    header('location:' . $url);
    exit;
}

// Нормальный вид загружаемых файлов
function normalizeFilesArray($files = [])
{
    $result = [];

    foreach ($files as $file) {
        if (!is_array($file['name'])) {
            $result[] = $file;
            continue;
        }

        foreach ($file['name'] as $key => $filename) {
            $result[$key] = [
                'name'      => $filename,
                'type'      => $file['type'][$key],
                'tmp_name'  => $file['tmp_name'][$key],
                'error'     => $file['error'][$key],
                'size'      => $file['size'][$key]
            ];
        }
    }

    return $result;
}

include 'index.phtml';