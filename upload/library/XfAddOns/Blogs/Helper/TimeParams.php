<?php

/**
 * Helper class. Manipulates time information.
 */
class XfAddOns_Blogs_Helper_TimeParams
{

	/**
	 * Get the date and time from the input, and rebuild the time that should be used for the post
	 * We get this from the form, and it is used to update the post data
	 */
	public static function getPostDateFromRequest($input)
	{
		$data = $input->filter(array(
			'post_date' => XenForo_Input::DATE_TIME,
			'hour' => XenForo_Input::UINT,
			'minute' => XenForo_Input::UINT,
			'second' => XenForo_Input::UINT
			));

		if (!isset($data['post_date']))	// if date is not set, we will return 0
		{
			return 0;
		}
		return $data['post_date'] + $data['hour'] * 3600 + $data['minute'] * 60 + $data['second'];
	}

	/**
	 * Adds the hours, minutes, seconds parameters
	 * @param array $viewParams
	 * @param array $millis		The post date
	 */
	public static function addTimeParams(&$viewParams, $millis = null)
	{
		// get array for hours and minutes
		$hours = array();
		for ($i = 0; $i <= 23; $i++) {
			$hours[] = $i;
		}
		$minutes = array();
		for ($i = 0; $i <= 59; $i++) {
			$minutes[] = $i;
		}
		$seconds = array();
		for ($i = 0; $i <= 59; $i++) {
			$seconds[] = $i;
		}

		$viewParams['hours'] = $hours;
		$viewParams['minutes'] = $minutes;
		$viewParams['seconds'] = $seconds;
		if ($millis > 0)
		{
			$timeZone = new DateTimeZone(XenForo_Visitor::getInstance()->get('timezone'));
			if (function_exists('date_timestamp_set'))
			{
				$dateObj = new DateTime('', $timeZone);		// PHP 5.2
				$dateObj->setTimestamp($millis);
			}
			else
			{
				$dateObj = new DateTime('');
				$date = getdate( ( int ) $millis );
				$dateObj->setDate( $date['year'] , $date['mon'] , $date['mday'] );
        		$dateObj->setTime( $date['hours'] , $date['minutes'] , $date['seconds'] );
				$dateObj->setTimezone($timeZone);
			}

			$viewParams['display_date'] = $dateObj->format('Y-m-d');
			$viewParams['selectedHour'] = $dateObj->format('G');
			$viewParams['selectedMinute'] = $dateObj->format('i');
			$viewParams['selectedSecond'] = $dateObj->format('s');
		}
	}


}
