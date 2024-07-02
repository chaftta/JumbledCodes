<?php

/** PNG(CgBIチャンク付きPNG)を標準的なPNG形式に変換するクラス */
class Png {
	/**
	 * PNGファイルか判定する
	 * @param string $data PNGデータ
	 * @return bool true: png false: 非png
	 */
	function isPng(string $data): bool {
		// PNGシグネチャを確認（最初の8バイト）
		return substr($data, 0, 8) === "\x89PNG\r\n\x1a\n";
	}
	/**
	 * CgBIチャンクがあるか
	 * @param string $data
	 * @return bool true: ある false: ない
	 */
	function hasCgBIChunk(string $data): bool {
		// CgBIチャンクは必ず先頭にあるらしい
		return (substr($data, 12, 4) === 'CgBI');
	}
	/**
	 * rawデフレートストリームを解凍
	 *
	 * @param string $data 圧縮されたデータ
	 * @return string 解凍されたデータ
	 * @throws Exception 解凍に失敗した場合
	 */
	function rawDeflateDecompress(string $data): string {
		if (!function_exists('inflate_init')) {
			throw new Exception("This PHP installation does not support inflate_init. PHP 7.2.0 or later is required.");
		}

		$resource = inflate_init(ZLIB_ENCODING_RAW);
		if ($resource === false) {
			throw new Exception("Failed to initialize inflate");
		}

		$result = inflate_add($resource, $data, ZLIB_FINISH);
		if ($result === false) {
			throw new Exception("Decompression failed");
		}

		return $result;
	}
	/**
	 * ApplePNGから標準PNGに変換
	 *
	 * @param string $srcPath 入力PNGファイルのパス
	 * @param string $dstPath 出力PNGファイルのパス
	 * @throws Exception ファイルの読み込みに失敗した場合
	 */
	public function convert(string $srcPath, string $dstPath): void {
		// PNGの読み込み
		$srcBytes = file_get_contents($srcPath);
		// PNGの確認
		if (!$this->isPng($srcBytes)) return;
		// CgBIがある場合は、変換する。(ない場合はそのままのデータを保存する)
		$dstBytes = $this->hasCgBIChunk($srcBytes) ? $this->getImage($srcBytes) : $srcBytes;
		// PNGを保存
		file_put_contents($dstPath, $dstBytes);
	}
	/**
	 * PNGを保存する(CgBIチャンクがある場合は標準化して保存する)
	 *
	 * @param string $srcBytes 入力PNG
	 * @return string PNGデータ
	 * @throws Exception ファイルの読み込みに失敗した場合
	 */
	public function getImage(string $srcBytes): string {
		// ヘッダ処理
		$signatureHeader = substr($srcBytes, 0, 8);
		$newPNG = $signatureHeader;
		$currentByte = 8;
		// 初期化
		$cgbi = false;
		$iDatRaw = '';
		$IDATTypeRaw = '';
		$imgWidth = 0;
		$imgHeight = 0;
		// チャンク全てを処理
		while ($currentByte < strlen($srcBytes)) {
			// チャンクヘッダ
			$chunkLengthRaw = substr($srcBytes, $currentByte, 4);
			$chunkLength = unpack('N', $chunkLengthRaw)[1];
			$currentByte += 4;
			$chunkTypeRaw = substr($srcBytes, $currentByte, 4);
			$chunkType = $chunkTypeRaw;
			$currentByte += 4;

			// チャンクデータの処理
			$chunkData = substr($srcBytes, $currentByte, $chunkLength);
			$chunkCRC = substr($srcBytes, $currentByte + $chunkLength, 4);

			if ($chunkType === 'CgBI') {
				// CgBIチャンク
				$currentByte += $chunkLength + 4;
				$cgbi = true;
				continue;
			} elseif ($chunkType === 'IHDR') {
				// IHDRチャンク
				$iDat = unpack('Nwidth/Nheight/Cbitd/Ccolort/Ccompm/Cfilterm/Cinterlacem', $chunkData);
				$imgWidth = $iDat['width'];
				$imgHeight = $iDat['height'];
			} elseif ($chunkType === 'IDAT') {
				// IDATチャンク
				$IDATTypeRaw = $chunkTypeRaw;
				$iDatRaw .= $chunkData;
				$currentByte += $chunkLength + 4;
				continue;
			} elseif ($chunkType === 'IEND') {
				// IENDチャンク
				try {
					if ($cgbi) {
						$chunkIDAT = $this->rawDeflateDecompress($iDatRaw);
					} else {
						$chunkIDAT = gzuncompress($iDatRaw);
					}
				} catch (Exception $e) {
					throw new Exception('Error resolving IDAT chunk!');
				}

				if ($chunkIDAT === false) {
					throw new Exception('Error resolving IDAT chunk!');
				}

				$pngDat = '';
				$stride = $imgWidth * 4 + 1;
				for ($y = 0; $y < $imgHeight; $y++) {
					$pngDat .= $chunkIDAT[$y * $stride]; // Copy filter byte
					for ($x = 0; $x < $imgWidth; $x++) {
						$pixelStart = $y * $stride + 1 + $x * 4;
						if ($cgbi) {
							// CgBIはRGBAの並び順を元に戻して出力
							$pngDat .= $chunkIDAT[$pixelStart + 2];  // Red
							$pngDat .= $chunkIDAT[$pixelStart + 1];  // Green
							$pngDat .= $chunkIDAT[$pixelStart];      // Blue
							$pngDat .= $chunkIDAT[$pixelStart + 3];  // Alpha
						} else {
							// 標準のPNGはそのまま出力
							$pngDat .= substr($chunkIDAT, $pixelStart, 4);
						}
					}
				}
				// 圧縮
				$chunkIDAT = gzcompress($pngDat);
				$chunkLengthRaw = pack('N', strlen($chunkIDAT));
				// 検査合計
				$newCRC = crc32($IDATTypeRaw . $chunkIDAT);
				$newPNG .= $chunkLengthRaw . $IDATTypeRaw . $chunkIDAT . pack('N', $newCRC);
			}

			if ($chunkType !== 'IDAT') {
				// 検査合計
				$newCRC = crc32($chunkTypeRaw . $chunkData);
				$newPNG .= $chunkLengthRaw . $chunkTypeRaw . $chunkData . pack('N', $newCRC);
			}
			$currentByte += $chunkLength + 4;
		}
		return $newPNG;
	}
}
