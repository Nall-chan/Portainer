<?php

declare(strict_types=1);
	class PortainerIO extends IPSModuleStrict
	{
		public function Create(): void	
		{
			//Never delete this line!
			parent::Create();
		}

		public function Destroy(): void
		{
			//Never delete this line!
			parent::Destroy();
		}

		public function ApplyChanges(): void
		{
			//Never delete this line!
			parent::ApplyChanges();
		}

		public function ForwardData(string $JSONString): string
		{
			$data = json_decode($JSONString);
			IPS_LogMessage('IO FRWD', utf8_decode($data->Buffer));
			return '';
		}

		public function Send(string $Text): void
		{
			$this->SendDataToChildren(json_encode(['DataID' => '{80CBC81C-3722-EFA3-BF6F-D7CC9B0E0076}', 'Buffer' => $Text]));
		}
	}