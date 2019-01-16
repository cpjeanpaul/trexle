<?php

namespace Trexle\Payment\Helper;

class Utils extends \Magento\Framework\App\Helper\AbstractHelper
{

    /**
     * @var \Magento\Framework\Encryption\EncryptorInterface
     */
    protected $_encryptor;

    /**
     * @var \Magento\Directory\Model\Country
     */
    protected $_country;


    public function __construct(
        \Magento\Framework\Encryption\EncryptorInterface $encryptor,
        \Magento\Directory\Model\CountryFactory $country
    )
    {
        $this->_encryptor = $encryptor;
        $this->_country = $country;
    }

    /**
     * @param $currencyCode string
     * @param $amount float
     * @return integer
     */
    public function getRequestAmount($currencyCode, $amount)
    {
        // Round to avoid issue where number of cents is a decimal due to
        // floating-point precision errors.
        return round($amount * 100);
    }


    /**
     * @param $key string
     * @retun string
     */
    public function decrypt($key)
    {
        return $this->_encryptor->decrypt($key);
    }

    /**
     * @param $countryCode string
     * @retun string
     */
    public function getCountryName($countryCode)
    {
        $country = $this->_country->create()->loadByCode($countryCode);
        return $country->getName();
    }

}