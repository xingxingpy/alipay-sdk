<?php

namespace Xingxingpy\AlipaySdk\v2\aop\request;
/**
 * ALIPAY API: datadigital.fincloud.generalsaas.ocr.common.detect request
 *
 * @author auto create
 * @since 1.0, 2024-05-27 10:57:02
 */
class DatadigitalFincloudGeneralsaasOcrCommonDetectRequest
{
	/** 
	 * 被识别图片的二进制流
	 **/
	private $fileContent;
	
	/** 
	 * 可识别OCR类型
	 **/
	private $ocrType;
	
	/** 
	 * 客户业务单据号，请保证幂等性。
	 **/
	private $outerOrderNo;

	private $apiParas = array();
	private $terminalType;
	private $terminalInfo;
	private $prodCode;
	private $apiVersion="1.0";
	private $notifyUrl;
	private $returnUrl;
    private $needEncrypt=false;

	
	public function setFileContent($fileContent)
	{
		$this->fileContent = $fileContent;
		$this->apiParas["file_content"] = $fileContent;
	}

	public function getFileContent()
	{
		return $this->fileContent;
	}

	public function setOcrType($ocrType)
	{
		$this->ocrType = $ocrType;
		$this->apiParas["ocr_type"] = $ocrType;
	}

	public function getOcrType()
	{
		return $this->ocrType;
	}

	public function setOuterOrderNo($outerOrderNo)
	{
		$this->outerOrderNo = $outerOrderNo;
		$this->apiParas["outer_order_no"] = $outerOrderNo;
	}

	public function getOuterOrderNo()
	{
		return $this->outerOrderNo;
	}

	public function getApiMethodName()
	{
		return "datadigital.fincloud.generalsaas.ocr.common.detect";
	}

	public function setNotifyUrl($notifyUrl)
	{
		$this->notifyUrl=$notifyUrl;
	}

	public function getNotifyUrl()
	{
		return $this->notifyUrl;
	}

	public function setReturnUrl($returnUrl)
	{
		$this->returnUrl=$returnUrl;
	}

	public function getReturnUrl()
	{
		return $this->returnUrl;
	}

	public function getApiParas()
	{
		return $this->apiParas;
	}

	public function getTerminalType()
	{
		return $this->terminalType;
	}

	public function setTerminalType($terminalType)
	{
		$this->terminalType = $terminalType;
	}

	public function getTerminalInfo()
	{
		return $this->terminalInfo;
	}

	public function setTerminalInfo($terminalInfo)
	{
		$this->terminalInfo = $terminalInfo;
	}

	public function getProdCode()
	{
		return $this->prodCode;
	}

	public function setProdCode($prodCode)
	{
		$this->prodCode = $prodCode;
	}

	public function setApiVersion($apiVersion)
	{
		$this->apiVersion=$apiVersion;
	}

	public function getApiVersion()
	{
		return $this->apiVersion;
	}

  public function setNeedEncrypt($needEncrypt)
  {

     $this->needEncrypt=$needEncrypt;

  }

  public function getNeedEncrypt()
  {
    return $this->needEncrypt;
  }

}
