<?php
/**
 * Export-Import Functions
 */

/**
 * Export and Download Excel file
 *
 * @param $xeroomOrderArray
 * @param $xeroomPaymentStatus
 * @param $xeroomRepeatStatus
 */
function xeroomDownloadExportOrders( $xeroomOrderArray, $xeroomPaymentStatus, $xeroomRepeatStatus ) {
	ob_clean();
	$xeroomExcelFile = 'Export_order_list_' . date( "d_m_Y" ) . '.xlsx';
	$objPHPExcel     = new PHPExcel();

	$objPHPExcel->getProperties()->setCreator( "WooCommerce" )->setLastModifiedBy( "WooCommerce" )->setTitle( "Office 2007 XLSX Orders Document" )->setSubject( "Office 2007 XLSX Orders Document" )->setDescription( "Document for order list." )->setKeywords( "office 2007 openxml php" )->setCategory( "Orders List File" );

	$objPHPExcel->setActiveSheetIndex( 0 )
	            ->setCellValue( 'A1', 'S.No' )
	            ->setCellValue( 'B1', 'Customer Name' )
	            ->setCellValue( 'C1', 'Order Id' )
	            ->setCellValue( 'D1', 'Invoice Status' )
	            ->setCellValue( 'E1', 'Repeat Status' );

	$xeroomUserName = '';
	for ( $i = 0; $i < count( $xeroomOrderArray ); $i ++ ) {
		$zeroom_r       = $i + 2;
		$zeroom_s       = $i + 1;
		$xeroomUserData = get_userdata( get_post_meta( $xeroomOrderArray[ $i ], '_customer_user', true ) );

		if ( $xeroomUserData ) {
			$xeroomUserName = $xeroomUserData->data->display_name;
		}

		$objPHPExcel->setActiveSheetIndex( 0 )
		            ->setCellValue( "A" . $zeroom_r, $zeroom_s )
		            ->setCellValue( "B" . $zeroom_r, $xeroomUserName )
		            ->setCellValue( "C" . $zeroom_r, $xeroomOrderArray[ $i ] )
		            ->setCellValue( "D" . $zeroom_r, $xeroomPaymentStatus )
		            ->setCellValue( "E" . $zeroom_r, $xeroomRepeatStatus );
	}

	$objPHPExcel->getActiveSheet()->setTitle( 'Xero Orders List' );
	$objPHPExcel->setActiveSheetIndex( 0 );
	header( 'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' );
	header( 'Content-Disposition: attachment;filename="' . $xeroomExcelFile . '"' );
	header( 'Cache-Control: max-age=0' );
	header( 'Cache-Control: max-age=1' );
	header( 'Expires: Mon, 26 Jul 1997 05:00:00 GMT' );
	header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s' ) . ' GMT' );
	header( 'Cache-Control: cache, must-revalidate' );
	header( 'Pragma: public' );
	$objWriter = PHPExcel_IOFactory::createWriter( $objPHPExcel, 'Excel2007' );
	$objWriter->save( 'php://output' );
	exit();
}

/**
 *
 * @param $xeroomOrderArray
 */
function xeroomDownloadExportLogFile( $xeroomOrderArray ) {
	global $wpdb;
	ob_clean();
	$xeroomExcelFile = "product-orders-" . date( "Ymd-H:i" ) . '.xlsx';
	$objPHPExcel     = new PHPExcel();

	$objPHPExcel->getProperties()->setCreator( "WooCommerce" )->setLastModifiedBy( "WooCommerce" )->setTitle( "Office 2007 XLSX Orders Document" )->setSubject( "Office 2007 XLSX Orders Document" )->setDescription( "Document for order list." )->setKeywords( "office 2007 openxml php" )->setCategory( "Orders List File" );

	$objPHPExcel->setActiveSheetIndex( 0 )
	            ->setCellValue( 'A1', 'S.No' )
	            ->setCellValue( 'B1', 'Customer Name' )
	            ->setCellValue( 'C1', 'Order Id' )
	            ->setCellValue( 'D1', 'Invoice Status' )
	            ->setCellValue( 'E1', 'Repeat Status' )
	            ->setCellValue( 'F1', 'Export Status' );

	$xeroomUserName = '';
	for ( $i = 0; $i < count( $xeroomOrderArray ); $i ++ ) {
		$zeroom_r       = $i + 2;
		$zeroom_s       = $i + 1;
		$xeroomUserData = get_userdata( get_post_meta( $xeroomOrderArray[ $i ]->order_id, '_customer_user', true ) );

		if ( $xeroomUserData ) {
			$xeroomUserName = $xeroomUserData->data->display_name;
		}

		$objPHPExcel->setActiveSheetIndex( 0 )
		            ->setCellValue( "A" . $zeroom_r, $zeroom_s )
		            ->setCellValue( "B" . $zeroom_r, $xeroomUserName )
		            ->setCellValue( "C" . $zeroom_r, $xeroomOrderArray[ $i ]->order_id )
		            ->setCellValue( "D" . $zeroom_r, $xeroomOrderArray[ $i ]->payment_type )
		            ->setCellValue( "E" . $zeroom_r, $xeroomOrderArray[ $i ]->ignore_type )
		            ->setCellValue( "F" . $zeroom_r, $xeroomOrderArray[ $i ]->status );
	}

	$objPHPExcel->getActiveSheet()->setTitle( 'Xero Orders List' );
	$objPHPExcel->setActiveSheetIndex( 0 );
	header( 'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' );
	header( 'Content-Disposition: attachment;filename="' . $xeroomExcelFile . '"' );
	header( 'Cache-Control: max-age=0' );
	header( 'Cache-Control: max-age=1' );
	header( 'Expires: Mon, 26 Jul 1997 05:00:00 GMT' );
	header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s' ) . ' GMT' );
	header( 'Cache-Control: cache, must-revalidate' );
	header( 'Pragma: public' );
	$objWriter = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter( $objPHPExcel, 'Excel2007' );
	$objWriter->save( 'php://output' );
	exit();
}

/**
 * Class XLSXReader
 */
class XLSXReader {
	protected $sheets = array();
	protected $sharedstrings = array();
	protected $sheetInfo;
	protected $zip;
	public $config = array( 'removeTrailingRows' => true );
	const SCHEMA_OFFICEDOCUMENT = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument';
	const SCHEMA_RELATIONSHIP = 'http://schemas.openxmlformats.org/package/2006/relationships';
	const SCHEMA_OFFICEDOCUMENT_RELATIONSHIP = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
	const SCHEMA_SHAREDSTRINGS = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings';
	const SCHEMA_WORKSHEETRELATION = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet';

	/**
	 * XLSXReader constructor.
	 *
	 * @param $filePath
	 * @param array $config
	 *
	 * @throws Exception
	 */
	public function __construct( $filePath, $config = array() ) {
		$this->config = array_merge( $this->config, $config );
		$this->zip    = new ZipArchive();
		$status       = $this->zip->open( $filePath );
		if ( $status === true ) {
			$this->parse();
		} else {
			throw new Exception( "Failed to open $filePath with zip error code: $status" );
		}
	}

	/**
	 * @param $name
	 *
	 * @return string
	 * @throws Exception
	 */
	protected function getEntryData( $name ) {
		$data = $this->zip->getFromName( $name );
		if ( $data === false ) {
			throw new Exception( "File $name does not exist in the Excel file" );
		} else {
			return $data;
		}
	}

	/**
	 * @throws Exception
	 */
	protected function parse() {
		$sheets           = array();
		$relationshipsXML = simplexml_load_string( $this->getEntryData( "_rels/.rels" ) );
		foreach ( $relationshipsXML->Relationship as $rel ) {
			if ( $rel['Type'] == self::SCHEMA_OFFICEDOCUMENT ) {
				$workbookDir = dirname( $rel['Target'] ) . '/';
				$workbookXML = simplexml_load_string( $this->getEntryData( $rel['Target'] ) );
				foreach ( $workbookXML->sheets->sheet as $sheet ) {
					$r                         = $sheet->attributes( 'r', true );
					$sheets[ (string) $r->id ] = array(
						'sheetId' => (int) $sheet['sheetId'],
						'name'    => (string) $sheet['name']
					);

				}
				$workbookRelationsXML = simplexml_load_string( $this->getEntryData( $workbookDir . '_rels/' . basename( $rel['Target'] ) . '.rels' ) );
				foreach ( $workbookRelationsXML->Relationship as $wrel ) {
					switch ( $wrel['Type'] ) {
						case self::SCHEMA_WORKSHEETRELATION:
							$sheets[ (string) $wrel['Id'] ]['path'] = $workbookDir . (string) $wrel['Target'];
							break;
						case self::SCHEMA_SHAREDSTRINGS:
							$sharedStringsXML = simplexml_load_string( $this->getEntryData( $workbookDir . (string) $wrel['Target'] ) );
							foreach ( $sharedStringsXML->si as $val ) {
								if ( isset( $val->t ) ) {
									$this->sharedStrings[] = (string) $val->t;
								} elseif ( isset( $val->r ) ) {
									$this->sharedStrings[] = XLSXWorksheet::parseRichText( $val );
								}
							}
							break;
					}
				}
			}
		}
		$this->sheetInfo = array();
		foreach ( $sheets as $rid => $info ) {
			$this->sheetInfo[ $info['name'] ] = array(
				'sheetId' => $info['sheetId'],
				'rid'     => $rid,
				'path'    => $info['path']
			);
		}
	}

	/**
	 * @return array
	 */
	public function getSheetNames() {
		$res = array();
		foreach ( $this->sheetInfo as $sheetName => $info ) {
			$res[ $info['sheetId'] ] = $sheetName;
		}

		return $res;
	}

	/**
	 * @return int
	 */
	public function getSheetCount() {
		return count( $this->sheetInfo );
	}

	/**
	 * @param $sheetNameOrId
	 *
	 * @return mixed
	 * @throws Exception
	 */
	public function getSheetData( $sheetNameOrId ) {
		$sheet = $this->getSheet( $sheetNameOrId );

		return $sheet->getData();
	}

	/**
	 * @param $sheet
	 *
	 * @return mixed
	 * @throws Exception
	 */
	public function getSheet( $sheet ) {
		if ( is_numeric( $sheet ) ) {
			$sheet = $this->getSheetNameById( $sheet );
		} elseif ( ! is_string( $sheet ) ) {
			throw new Exception( "Sheet must be a string or a sheet Id" );
		}
		if ( ! array_key_exists( $sheet, $this->sheets ) ) {
			$this->sheets[ $sheet ] = new XLSXWorksheet( $this->getSheetXML( $sheet ), $sheet, $this );

		}

		return $this->sheets[ $sheet ];
	}

	/**
	 * @param $sheetId
	 *
	 * @return int|string
	 * @throws Exception
	 */
	public function getSheetNameById( $sheetId ) {
		foreach ( $this->sheetInfo as $sheetName => $sheetInfo ) {
			if ( $sheetInfo['sheetId'] === $sheetId ) {
				return $sheetName;
			}
		}
		throw new Exception( "Sheet ID $sheetId does not exist in the Excel file" );
	}

	/**
	 * @param $name
	 *
	 * @return SimpleXMLElement
	 * @throws Exception
	 */
	protected function getSheetXML( $name ) {
		return simplexml_load_string( $this->getEntryData( $this->sheetInfo[ $name ]['path'] ) );
	}

	/**
	 * @param $excelDateTime
	 *
	 * @return float|int
	 */
	public static function toUnixTimeStamp( $excelDateTime ) {
		if ( ! is_numeric( $excelDateTime ) ) {
			return $excelDateTime;
		}
		$d = floor( $excelDateTime );
		$t = $excelDateTime - $d;

		return ( $d > 0 ) ? ( $d - 25569 ) * 86400 + $t * 86400 : $t * 86400;
	}
}

/**
 * Class XLSXWorksheet
 */
class XLSXWorksheet {
	protected $workbook;
	public $sheetName;
	protected $data;
	public $colCount;
	public $rowCount;
	protected $config;

	/**
	 * XLSXWorksheet constructor.
	 *
	 * @param $xml
	 * @param $sheetName
	 * @param XLSXReader $workbook
	 */
	public function __construct( $xml, $sheetName, XLSXReader $workbook ) {
		$this->config    = $workbook->config;
		$this->sheetName = $sheetName;
		$this->workbook  = $workbook;
		$this->parse( $xml );
	}

	/**
	 * @return mixed
	 */
	public function getData() {
		return $this->data;
	}

	/**
	 * @param $xml
	 */
	protected function parse( $xml ) {
		$this->parseDimensions( $xml->dimension );
		$this->parseData( $xml->sheetData );
	}

	/**
	 * @param $dimensions
	 *
	 * @throws Exception
	 */
	protected function parseDimensions( $dimensions ) {
		$range          = (string) $dimensions['ref'];
		$cells          = explode( ':', $range );
		$maxValues      = $this->getColumnIndex( $cells[1] );
		$this->colCount = $maxValues[0] + 1;
		$this->rowCount = $maxValues[1] + 1;
	}

	/**
	 * @param $sheetData
	 *
	 * @throws Exception
	 */
	protected function parseData( $sheetData ) {
		$rows        = array();
		$curR        = 0;
		$lastDataRow = - 1;
		foreach ( $sheetData->row as $row ) {
			$rowNum = (int) $row['r'];
			if ( $rowNum != ( $curR + 1 ) ) {
				$missingRows = $rowNum - ( $curR + 1 );
				for ( $i = 0; $i < $missingRows; $i ++ ) {
					$rows[ $curR ] = array_pad( array(), $this->colCount, null );
					$curR ++;
				}
			}
			$curC    = 0;
			$rowData = array();
			foreach ( $row->c as $c ) {
				list( $cellIndex, ) = $this->getColumnIndex( (string) $c['r'] );
				if ( $cellIndex !== $curC ) {
					$missingCols = $cellIndex - $curC;
					for ( $i = 0; $i < $missingCols; $i ++ ) {
						$rowData[ $curC ] = null;
						$curC ++;
					}
				}
				$val = $this->parseCellValue( $c );
				if ( ! is_null( $val ) ) {
					$lastDataRow = $curR;
				}
				$rowData[ $curC ] = $val;
				$curC ++;
			}
			$rows[ $curR ] = array_pad( $rowData, $this->colCount, null );
			$curR ++;
		}
		if ( $this->config['removeTrailingRows'] ) {
			$this->data     = array_slice( $rows, 0, $lastDataRow + 1 );
			$this->rowCount = count( $this->data );
		} else {
			$this->data = $rows;
		}
	}

	/**
	 * @param string $cell
	 *
	 * @return array
	 * @throws Exception
	 */
	protected function getColumnIndex( $cell = 'A1' ) {
		if ( preg_match( "/([A-Z]+)(\d+)/", $cell, $matches ) ) {

			$col    = $matches[1];
			$row    = $matches[2];
			$colLen = strlen( $col );
			$index  = 0;

			for ( $i = $colLen - 1; $i >= 0; $i -- ) {
				$index += ( ord( $col[ $i ] ) - 64 ) * pow( 26, $colLen - $i - 1 );
			}

			return array( $index - 1, $row - 1 );
		}
		throw new Exception( "Invalid cell index" );
	}

	/**
	 * @param $cell
	 *
	 * @return bool|string
	 */
	protected function parseCellValue( $cell ) {
		switch ( (string) $cell["t"] ) {
			case "s":
				if ( (string) $cell->v != '' ) {
					$value = $this->workbook->sharedStrings[ intval( $cell->v ) ];
				} else {
					$value = '';
				}
				break;
			case "b":
				$value = (string) $cell->v;
				if ( $value == '0' ) {
					$value = false;
				} else if ( $value == '1' ) {
					$value = true;
				} else {
					$value = (bool) $cell->v;
				}
				break;
			case "inlineStr":
				$value = self::parseRichText( $cell->is );
				break;
			case "e":
				if ( (string) $cell->v != '' ) {
					$value = (string) $cell->v;
				} else {
					$value = '';
				}
				break;
			default:
				if ( ! isset( $cell->v ) ) {
					return null;
				}
				$value = (string) $cell->v;
				if ( is_numeric( $value ) ) {
					if ( $value == (int) $value ) {
						$value = (int) $value;
					} elseif ( $value == (float) $value ) {
						$value = (float) $value;
					} elseif ( $value == (double) $value ) {
						$value = (double) $value;
					}
				}
		}

		return $value;
	}

	/**
	 * @param null $is
	 *
	 * @return string
	 */
	public static function parseRichText( $is = null ) {
		$value = array();
		if ( isset( $is->t ) ) {
			$value[] = (string) $is->t;
		} else {
			foreach ( $is->r as $run ) {
				$value[] = (string) $run->t;
			}
		}

		return implode( ' ', $value );
	}
}
