<?php

declare(strict_types=1);
	class DockerContainer extends IPSModule
	{
		public function Create()
		{
			//Never delete this line!
			parent::Create();

			$this->RequireParent('{FEB4D3D2-AD8A-9C6A-72D8-DF99AC625768}');
		}

		public function Destroy()
		{
			//Never delete this line!
			parent::Destroy();
		}

		public function ApplyChanges()
		{
			//Never delete this line!
			parent::ApplyChanges();
		}

		public function Send()
		{
			$this->SendDataToParent(json_encode(['DataID' => '{A5558F9B-F5AE-A5B0-C252-328740544A21}']));
		}

		public function ReceiveData($JSONString)
		{
			$data = json_decode($JSONString);
			IPS_LogMessage('Device RECV', utf8_decode($data->Buffer));
		}
	}