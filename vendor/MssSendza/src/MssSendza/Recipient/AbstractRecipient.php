<?php
namespace MssSendza\Recipient;

abstract class AbstractRecipient
{
    protected $address;

    public function __construct($address)
    {
        $this->address = $address;
    }

    abstract public function getType();

    public function getData()
    {
        return array(
            'ContactMethodType' => $this->getType(),
            'Address'           => $this->getAddress()
        );
    }

	/**
     * Get address
     *
     * @return string
     */
    public function getAddress()
    {
        return $this->address;
    }

	/**
     * Set address
     *
     * @param string $address
     * @return MssSendza\Recipient\AbstractRecipient
     */
    public function setAddress($address)
    {
        $this->address = $address;
        return $this;
    }
}