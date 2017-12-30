<?php
require("ezmfi.class.php");
echo "Attempting login...";
$mfi = new ezmfi("192.168.1.100","username","password"); // PLEASE USE STRONG PASSWORDS
if ($mfi->mFIAuth == false) {
    echo "Login failed! See debug below:</br>\n";
    echo $mfi->mFILog;
    die;
}


echo "Login success!</br>\n";
echo "As a reminder, this library makes HTTP requests. Every time you make a request there is an added latency. The more requests you make, the longer it will take for your script to complete.</br>\n";

echo "</br>\n</br>\n";
echo "Simple outlet status array:</br>\n";
print_r($mfi->mFIOutletStatus);
echo "</br>\n</br>\n";

echo "Detailed outlet status array:</br>\n";
print_r($mfi->mFIOutletStatusDetail);
echo "</br>\n</br>\n";

echo "Turning on outlet 1...";
if ($mfi->setOutlet("1","ON")) {
    echo "Outlet 1 turned on!</br>\n";
} else {
    echo "Error turning on outlet 1!</br>\n";
}
echo "</br>\n</br>\n";
echo "When I set the outlet the outletStatusArray was updated, check it out now</br>\n";
print_r($mfi->mFIOutletStatus);
echo "</br>\n</br>\n";

echo "Or maybe you want to just refresh without setting or reading....";
if ($mfi->refresh()) {
    echo "Refreshed!</br>\n";
} else {
    echo "Refresh failed!</br>\n";
}
echo "</br>\n</br>\n";

echo "Perform an on demand read (also refreshes outlet status array)</br>\n";
echo "Outlet 1's power is: " . $mfi->readOutlet("1");
echo "</br>\n</br>\n";

echo "Bounce outlet (turn off then on if ON, turn on then off if OFF)....";
if ($mfi->setOutlet(1,"ccl")) {
    echo "Outlet 1 cycled on!</br>\n";
} else {
    echo "Error cycling outlet 1!</br>\n";
}
echo "</br>\n</br>\n";
echo "Have fun!</br>\n</br>\n</br>\n";

echo $mfi->mFILog;