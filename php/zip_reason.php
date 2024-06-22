<?php
const ZIP_REASON = [
	'ER_OK',
	'ER_MULTIDISK',
	'ER_RENAME',
	'ER_CLOSE',
	'ER_SEEK',
	'ER_READ',
	'ER_WRITE',
	'ER_CRC',
	'ER_ZIPCLOSED',
	'ER_NOENT',
	'ER_EXISTS',
	'ER_OPEN',
	'ER_TMPOPEN',
	'ER_ZLIB',
	'ER_MEMORY',
	'ER_CHANGED',
	'ER_COMPNOTSUPP',
	'ER_EOF',
	'ER_INVAL',
	'ER_NOZIP',
	'ER_INTERNAL',
	'ER_INCONS',
	'ER_REMOVE',
	'ER_DELETED',
];
/**
 * Zipのopenのエラーコード名を取得する
 * @param {int} $code リザルトコード
 * @return string エラー名
 */
function getZipReason($code): string {
	return ZIP_REASON[$code] ?? 'undefined';
}