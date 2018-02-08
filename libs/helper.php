<?

trait BufferHelper
{

/**
* Wert einer Eigenschaft aus den InstanceBuffer lesen.
*
* @access public
* @param string $name Propertyname
* @return mixed Value of Name
*/
public function __get($name)
{
return unserialize($this->GetBuffer($name));
}

/**
* Wert einer Eigenschaft in den InstanceBuffer schreiben.
*
* @access public
* @param string $name Propertyname
* @param mixed Value of Name
*/
public function __set($name, $value)
{
$this->SetBuffer($name, serialize($value));
}

}

trait DebugHelper
{

/**
* Ergänzt SendDebug um Möglichkeit Objekte und Array auszugeben.
*
* @access protected
* @param string $Message Nachricht für Data.
* @param mixed $Data Daten für die Ausgabe.
* @return int $Format Ausgabeformat für Strings.
*/
protected function SendDebug($Message, $Data, $Format)
{
if (is_object($Data))
{
foreach ($Data as $Key => $DebugData)
{

$this->SendDebug($Message . ":" . $Key, $DebugData, 0);
}
}
else if (is_array($Data))
{
foreach ($Data as $Key => $DebugData)
{
$this->SendDebug($Message . ":" . $Key, $DebugData, 0);
}
}
else if (is_bool($Data))
{
parent::SendDebug($Message, ($Data ? 'TRUE' : 'FALSE'), 0);
}
else
{
parent::SendDebug($Message, (string) $Data, $Format);
}
}

}