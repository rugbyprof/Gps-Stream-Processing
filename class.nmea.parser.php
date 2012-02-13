<?php
//////////////////////////////////////////////////////////////////////////////////////////////////////////////
//class.nmea.parser.php
///////////////////////////////////////////////////////////////////////////////////////////////////////////////
//	NmeaParser Class
//	Version: 1.0 
//	Author: Terry Griffin
//	Based on Code written by: David Boardman
//	URL: http://cs.mwsu.edu/~griffin
//	Licensed: GNU General Public License (GNU GPL)
//
//	Do what you want with the code. I'm not even sure if it's correct. 
///////////////////////////////////////////////////////////////////////////////////////////////////////////////

class NmeaParser{

	private $Nmea;  		//Nmea Array of data 
	private $TimeStamp;		//Unix time stamp
	private $maxHDOP;		//max horizontal dilution of precision
	private $maxVDOP;		//max horizontal dilution of precision
	private $CurrentUTC;	//Current time stamp to coordinate sentences	
	
	function __construct(){
		$this->Nmea 	= array();
		$this->TimeStamp= 0;
		$this->maxHDOP	= 0.0;
		$this->maxVDOP	= 0.0;
		$this->CurrentUTC = 0;
		$this->CurrentTime=0;
	}
	
	///////////////////////////////////////////////////////////////////////////////////////////////////////////
	//SetMinSatellites - Set the minimum satellite parameter to remove / ignore sentences that don't have
	//enough satellites for a decent fix.	
	//
	//@param - 		int 	$minSats - minimum satellites
	//@returns - 	void
	///////////////////////////////////////////////////////////////////////////////////////////////////////////
	public function SetMinSatellites($minSats=4){
		$this->minSats = $minSats;
	}
	
	//Dilution of precision:
	//1		Ideal		This is the highest possible confidence level to be used for applications demanding the highest possible precision at all times.
	//1-2	Excellent	At this confidence level, positional measurements are considered accurate enough to meet all but the most sensitive applications.
	//2-5	Good		Represents a level that marks the minimum appropriate for making business decisions. Positional measurements could be used to make reliable in-route navigation suggestions to the user.
	//5-10	Moderate	Positional measurements could be used for calculations, but the fix quality could still be improved. A more open view of the sky is recommended.
	//10-20	Fair		Represents a low confidence level. Positional measurements should be discarded or used only to indicate a very rough estimate of the current location.
	//>20	Poor		At this level, measurements are inaccurate by as much as 300 meters with a 6 meter accurate device (50 DOP × 6 meters) and should be discarded.
	
	
	///////////////////////////////////////////////////////////////////////////////////////////////////////////
	//SetMaxHdop - 
	//Set the maximum (smaller value = better) horizontal dilution of precision parameter to 
	//remove / ignore sentences that don't have enough satellites in the correct location in the sky for a 
	//decent fix.	
	//
	//@param - 		int 	$maxHDOP - Hdop value
	//@returns - 	void
	///////////////////////////////////////////////////////////////////////////////////////////////////////////
	public function SetMaxHdop($maxHDOP=10){
		$this->maxHDOP = $maxHDOP;
	}	
	
	///////////////////////////////////////////////////////////////////////////////////////////////////////////
	//SetMaxVdop - 
	//Set the maximum (smaller value = better) vertical dilution of precision parameter to 
	//remove / ignore sentences that don't have enough satellites in the correct location in the sky for a 
	//decent fix.	
	//
	//@param - 		int 	$maxVDOP - Hdop value
	//@returns - 	void
	///////////////////////////////////////////////////////////////////////////////////////////////////////////
	public function SetMaxVdop($maxVDOP=10){
		$this->maxVDOP = $maxVDOP;
	}	

	///////////////////////////////////////////////////////////////////////////////////////////////////////////
	//NMEAtoUnixTime - Convert Date and Time to Linux Timestamp
	//
	//@param - 		string 	$time - current utc(hhmmss)
	//@param -      int $date - current date (mmddyy)
	//@returns - 	int - timestamp
	///////////////////////////////////////////////////////////////////////////////////////////////////////////
	private function NMEAtoUnixTime($utc,$date){
		$h = substr($utc,0,2);
		$i = substr($utc,2,2);
		$s = substr($utc,4,2);
		$d = substr($date,0,2);
		$m = substr($date,2,2);
		$y = substr($date,4,2);		
		//list($y,$m,$d) = explode('-',$date);
		return mktime($h,$i,$s,$m,$d,$y);
	}
	
	///////////////////////////////////////////////////////////////////////////////////////////////////////////
	//ParseLine - Parse the current line 
	//
	//@param - 		string 	$line - current nmea line
	//@returns - 	void
	///////////////////////////////////////////////////////////////////////////////////////////////////////////
	public function ParseLine($line){
		$this->NmeaType = $this->SetNmeaType($line);
		switch($this->type){
			case "GPGGA": $this->GPGGA($line);break;
			case "GPGLL": $this->GPGLL($line);break;
			case "GPGSA": $this->GPGSA($line);break;
			case "GPGSV": $this->GPGSV($line);break;
			case "GPRMC": $this->GPRMC($line);break;
			case "GPVTG": $this->GPVTG($line);break;
			default: return;
		}
	}
	///////////////////////////////////////////////////////////////////////////////////////////////////////////
	//DumpNmea - Returns current Nmea data 
	//
	//@param - 		void
	//@returns - 	array - Nmea data
	///////////////////////////////////////////////////////////////////////////////////////////////////////////	
	public function DumpNmea(){
		return $this->Nmea;
	}
	
	
	function GoodEnough(){
		return isset($this->Nmea[$this->CurrentUTC]['date']) && isset($this->Nmea[$this->CurrentUTC]['utc']) && isset($this->Nmea[$this->CurrentUTC]['lat']) && isset($this->Nmea[$this->CurrentUTC]['long']);
	
	}
	
	///////////////////////////////////////////////////////////////////////////////////////////////////////////
	//NmeaType - GPGGA,GPGLL,GPGSA,GPGSV,GPRMC,GPVTG
	//
	//@param - 		int 	$NmeaType - what type of nmea sentence is it currently
	//@returns - 	void
	///////////////////////////////////////////////////////////////////////////////////////////////////////////
	private function SetNmeaType($line){
		$this->type = trim(strtoupper(substr($line,1,5)));
		return $this->type;
	}

	//////////////////////////////////////////////////////////////////////////////////////////////////////
	//GGA - essential fix data which provide 3D location and accuracy data.
	//
	// $GPGGA,123519,4807.038,N,01131.000,E,1,08,0.9,545.4,M,46.9,M,,*47
	//
	//Where:
	//     GGA          Global Positioning System Fix Data
	//     123519       Fix taken at 12:35:19 UTC
	//     4807.038,N   Latitude 48 deg 07.038' N
	//     01131.000,E  Longitude 11 deg 31.000' E
	//     1            Fix quality: 	0 = invalid
	//                              	1 = GPS fix (SPS)
	//                               	2 = DGPS fix
	//                               	3 = PPS fix
	//			       					4 = Real Time Kinematic
	//			       					5 = Float RTK
	//                               	6 = estimated (dead reckoning) (2.3 feature)
	//			       					7 = Manual input mode
	//			       					8 = Simulation mode
	//     08           Number of satellites being tracked
	//     0.9          Horizontal dilution of position
	//     545.4,M      Altitude, Meters, above mean sea level
	//     46.9,M       Height of geoid (mean sea level) above WGS84
	//                      ellipsoid
	//     (empty field) time in seconds since last DGPS update
	//     (empty field) DGPS station ID number
	//     *47          the checksum data, always begins with *	
	//////////////////////////////////////////////////////////////////////////////////////////////////////	
	private function GPGGA($geostr){
		$split=explode(",",$geostr);
		$this->CurrentUTC = $this->fixUTC($split[1]);			
		$this->Nmea[$this->CurrentUTC]['type']['GPGGA']=true;
		$this->Nmea[$this->CurrentUTC]['utc']=$this->fixUTC($split[1]);
		$this->Nmea[$this->CurrentUTC]['lat']=$this->degree2decimal($split[2],$split[3]);
		$this->Nmea[$this->CurrentUTC]['ns']=$split[3];
		$this->Nmea[$this->CurrentUTC]['long']=$this->degree2decimal($split[4],$split[5]);
		$this->Nmea[$this->CurrentUTC]['ew']=$split[5];
		$this->Nmea[$this->CurrentUTC]['gpsqual']=$split[6];
		$this->Nmea[$this->CurrentUTC]['numsat']=$split[7];
		$this->Nmea[$this->CurrentUTC]['hdp']=$split[8];
		$this->Nmea[$this->CurrentUTC]['alt']=$split[9];
		$this->Nmea[$this->CurrentUTC]['un_alt']=$split[10];
		$this->Nmea[$this->CurrentUTC]['geoidal']=$split[11];
		$this->Nmea[$this->CurrentUTC]['un_geoidal']=$split[12];
		$this->Nmea[$this->CurrentUTC]['dgps']=$split[13];
		$this->Nmea[$this->CurrentUTC]['diffstat']=trim($split[14]);
	}

	//////////////////////////////////////////////////////////////////////////////////////////////////////
	//  $GPGLL,4916.45,N,12311.12,W,225444,A,*1D
	//
	//Where:
	//     GLL          Geographic position, Latitude and Longitude
	//     4916.46,N    Latitude 49 deg. 16.45 min. North
	//     12311.12,W   Longitude 123 deg. 11.12 min. West
	//     225444       Fix taken at 22:54:44 UTC
	//     A            Data Active or V (void)
	//     *iD          checksum data
	//////////////////////////////////////////////////////////////////////////////////////////////////////
	private function GPGLL($geostr){
		$split=explode(",",$geostr);
		$this->Nmea[$this->CurrentUTC]['type']['GPGLL']=true;
		$this->CurrentUTC = $this->fixUTC($split[3]);
		$this->Nmea[$this->CurrentUTC]['utc']=$this->fixUTC($split[3]);
		$this->Nmea[$this->CurrentUTC]['status']=$this->dataStatus($split[4]);
	}

	//////////////////////////////////////////////////////////////////////////////////////////////////////
	//  $GPGSA,A,3,04,05,,09,12,,,24,,,,,2.5,1.3,2.1*39
	//
	//Where:
	//     GSA      Satellite status
	//     A        Auto selection of 2D or 3D fix (M = manual) 
	//     3        3D fix - values include: 1 = no fix
	//                                       2 = 2D fix
	//                                       3 = 3D fix
	//     04,05... PRNs of satellites used for fix (space for 12) 
	//     2.5      PDOP (dilution of precision) 
	//     1.3      Horizontal dilution of precision (HDOP) 
	//     2.1      Vertical dilution of precision (VDOP)
	//     *39      the checksum data, always begins with *
	//////////////////////////////////////////////////////////////////////////////////////////////////////
	private function GPGSA($geostr){ 
		$split=explode(",",$geostr);
		$this->Nmea[$this->CurrentUTC]['type']['GPGSA']=true;
		$this->Nmea[$this->CurrentUTC]['selectmode']=$split[1];
		$this->Nmea[$this->CurrentUTC]['mode']=$split[2];
		$this->Nmea[$this->CurrentUTC]['sat1']=$split[3];
		$this->Nmea[$this->CurrentUTC]['sat2']=$split[4];
		$this->Nmea[$this->CurrentUTC]['sat3']=$split[5];
		$this->Nmea[$this->CurrentUTC]['sat4']=$split[6];
		$this->Nmea[$this->CurrentUTC]['sat5']=$split[7];
		$this->Nmea[$this->CurrentUTC]['sat6']=$split[8];
		$this->Nmea[$this->CurrentUTC]['sat7']=$split[9];
		$this->Nmea[$this->CurrentUTC]['sat8']=$split[10];
		$this->Nmea[$this->CurrentUTC]['sat9']=$split[11];
		$this->Nmea[$this->CurrentUTC]['sat10']=$split[12];
		$this->Nmea[$this->CurrentUTC]['sat11']=$split[13];
		$this->Nmea[$this->CurrentUTC]['sat12']=$split[14];
		$this->Nmea[$this->CurrentUTC]['pdop']=$split[15];
		$this->Nmea[$this->CurrentUTC]['hdop']=$split[16];
		$this->Nmea[$this->CurrentUTC]['vdop']=$split[17];
	}
	//////////////////////////////////////////////////////////////////////////////////////////////////////
	//  $GPGSV,2,1,08,01,40,083,46,02,17,308,41,12,07,344,39,14,22,228,45*75
	//
	//Where:
	//      GSV          Satellites in view
	//      2            Number of sentences for full data
	//      1            sentence 1 of 2
	//      08           Number of satellites in view
	//
	//      01           Satellite PRN number
	//      40           Elevation, degrees
	//      083          Azimuth, degrees
	//      46           SNR - higher is better
	//           for up to 4 satellites per sentence
	//      *75          the checksum data, always begins with *
	//////////////////////////////////////////////////////////////////////////////////////////////////////
	//*********needs fixing
	private function GPGSV($geostr){
		$split=explode(",",$geostr);
		$this->Nmea[$this->CurrentUTC]['type']['GPGSV']=true;
		$this->Nmea[$this->CurrentUTC]['satmessages']=$split[1];
		$this->Nmea[$this->CurrentUTC]['messnum']=$split[2];
		$this->Nmea[$this->CurrentUTC]['satview']=$split[3];
		$this->Nmea[$this->CurrentUTC]['satnum']=$split[4];
		$this->Nmea[$this->CurrentUTC]['elevdeg']=$split[5];
		$this->Nmea[$this->CurrentUTC]['azimuthdeg']=$split[6];
		$this->Nmea[$this->CurrentUTC]['snr']=$split[7];
	}

	//////////////////////////////////////////////////////////////////////////////////////////////////////
	//$GPRMC,123519,A,4807.038,N,01131.000,E,022.4,084.4,230394,003.1,W*6A
	//
	//Where:
	//     RMC          Recommended Minimum sentence C
	//     123519       Fix taken at 12:35:19 UTC
	//     A            Status A=active or V=Void.
	//     4807.038,N   Latitude 48 deg 07.038' N
	//     01131.000,E  Longitude 11 deg 31.000' E
	//     022.4        Speed over the ground in knots
	//     084.4        Track angle in degrees True
	//     230394       Date - 23rd of March 1994
	//     003.1,W      Magnetic Variation
	//     *6A          The checksum data, always begins with *
	//////////////////////////////////////////////////////////////////////////////////////////////////////
	private function GPRMC($geostr){
		$split=explode(",",$geostr);
		$this->CurrentUTC = $this->fixUTC($split[1]);
		$this->Nmea[$this->CurrentUTC]['utc']=$this->fixUTC($split[1]);
		$this->Nmea[$this->CurrentUTC]['type']['GPRMC']=true;
		$this->Nmea[$this->CurrentUTC]['statusrmc']=$split[2];
		$this->Nmea[$this->CurrentUTC]['speed']=$split[7];
		$this->Nmea[$this->CurrentUTC]['track']=$split[8];		
		$this->Nmea[$this->CurrentUTC]['date']=$split[9];
		$this->Nmea[$this->CurrentUTC]['magvar']=$split[10];
		$this->Nmea[$this->CurrentUTC]['mag_ew']=trim($split[11]);
		if($this->CurrentUTC && $split[9])
			$this->Nmea[$this->CurrentUTC]['Unix'] = $this->NMEAtoUnixTime($this->CurrentUTC,$split[9]);
	}
	
	//////////////////////////////////////////////////////////////////////////////////////////////////////	
	//VTG - Velocity made good. The gps receiver may use the LC prefix instead of GP if it is emulating Loran output.
	//
	//  $GPVTG,054.7,T,034.4,M,005.5,N,010.2,K*48
	//
	//where:
	//        VTG          Track made good and ground speed
	//        054.7,T      True track made good (degrees)
	//        034.4,M      Magnetic track made good
	//        005.5,N      Ground speed, knots
	//        010.2,K      Ground speed, Kilometers per hour
	//        *48          Checksum
	//////////////////////////////////////////////////////////////////////////////////////////////////////
	private function GPVTG($geostr){
		$split=explode(",",$geostr);
		$this->Nmea[$this->CurrentUTC]['type']['GPVTG']=true;
		$this->Nmea[$this->CurrentUTC]['trkdeg1']=$split[1];
		$this->Nmea[$this->CurrentUTC]['t']=$split[2];
		$this->Nmea[$this->CurrentUTC]['trkdeg2']=$split[3];
		$this->Nmea[$this->CurrentUTC]['m']=$split[4];
		$this->Nmea[$this->CurrentUTC]['spdknots']=$spdk=$split[5];
		$this->Nmea[$this->CurrentUTC]['knots']=$split[6];
		$this->Nmea[$this->CurrentUTC]['spdkmph']=$split[7];
		$this->Nmea[$this->CurrentUTC]['kph']=$split[8];
	}
	
	//////////////////////////////////////////////////////////////////////////////////////////////////////	
	//degree2decimal- 
	//Convert latitude and longitude in degrees minutes seconds format to decimal format.
	//E.g. = 4807.038,N would be 48.12722
	//Formula is as follows
	//	Degrees=Degrees
	//	.d = M.m/60
	//	Decimal Degrees=Degrees+.d	
	//////////////////////////////////////////////////////////////////////////////////////////////////////
    private function degree2decimal($deg_coord,$direction,$precision=6){
		$degree=(int)($deg_coord/100); //simple way
		$minutes= $deg_coord-($degree*100);
		$dotdegree=$minutes/60;
		$decimal=$degree+$dotdegree;
		//South latitudes and West longitudes need to return a negative result
        if (($direction=="S") or ($direction=="W"))
        {
    		$decimal=$decimal*(-1);
		}
    	$decimal=number_format($decimal,$precision,'.',''); //truncate decimal to $precision places
    	return $decimal;
	}	
	
	//////////////////////////////////////////////////////////////////////////////////////////////////////	
	//TimeChanged- 
	//@param int $Time - time value
	//@return bool  0,1 (If both time are not equal, then we are processing new nmea data.)
	//			
	//////////////////////////////////////////////////////////////////////////////////////////////////////
	private function TimeChanged($Time){
		return $this->CurrentTime==$Time;
	}
	
	//////////////////////////////////////////////////////////////////////////////////////////////////////	
	//GetNmeaData- 
	//@param void
	//@return array  - nmea data array
	//			
	//////////////////////////////////////////////////////////////////////////////////////////////////////
	public function GetNmeaData(){
		return $this->Nmea;
	}	
	
	//////////////////////////////////////////////////////////////////////////////////////////////////////	
	//fixUTCKey- 
	//Replaces keys based on UTC with a linux time stamp.
	//@param int $UTC - UTC time  (time only)
	//@param int $Unix - linux timestamp (has date)
	//////////////////////////////////////////////////////////////////////////////////////////////////////
	private function fixUTCKey($UTC,$Unix){
		//Not done
		$arr[$newkey] = $arr[$oldkey];
		unset($arr[$oldkey]);	
	}
	
	//////////////////////////////////////////////////////////////////////////////////////////////////////	
	//cleanUTC- 
	//If UTC has a decimal in it, get rid of it.
	//@param int $UTC - UTC time  (time only)
	//@return int $UTCfixed - 
	//////////////////////////////////////////////////////////////////////////////////////////////////////
	private function fixUTC($UTC){
		list($Fixed,$Null) = explode('.',$UTC);
		return $Fixed;
	}	
	
	
}

?>