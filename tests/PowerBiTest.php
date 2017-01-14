<?php 

use PowerBi\PowerBiWrapper;

/**
 * class PowerBiTest.
 */
class PowerBiTest extends PHPUnit_Framework_TestCase
{

    /**
     * Test Power BI library initialization
     * 
     * @return bool
     */
    public function testInitLibrary()
    {
        $powerBi = new PowerBiWrapper();
        $this->assertInstanceOf(PowerBiWrapper::class, $powerBi);
    }
}
