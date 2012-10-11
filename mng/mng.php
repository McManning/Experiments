<?php

	define('MNG_UINT_MHDR', 0x4d484452);
	define('MNG_UINT_BACK', 0x4241434b);
	define('MNG_UINT_PLTE', 0x504c5445);
	define('MNG_UINT_tRNS', 0x74524e53);
	define('MNG_UINT_IHDR', 0x49484452);
	define('MNG_UINT_IDAT', 0x49444154);
	define('MNG_UINT_IEND', 0x49454e44);
	define('MNG_UINT_MEND', 0x4d454e44);
	define('MNG_UINT_FRAM', 0x4652414d);
	define('MNG_UINT_LOOP', 0x4c4f4f50);
	define('MNG_UINT_ENDL', 0x454e444c);
	define('MNG_UINT_TERM', 0x5445524d);
	define('MNG_UINT_JHDR', 0x4a484452);
	define('MNG_UINT_tEXt', 0x74455874);
	
	define('MNG_SIGNATURE', "\212MNG\r\n\032\n");
	define('PNG_SIGNATURE', "\x89PNG\r\n\x1A\n");

	/** 
		Container for PNG related data. Stored in a way such that we can 
		manipulate in relation to the MNG container 
	*/
	class PNG_Object 
	{
		// Chunk objects we care about
		public $IHDR = null;
		public $PLTE = null; // PNGs will have a blank palette, replaced by MNG_Image::PLTE
		public $tEXt = null; // MNG-LC key storage
		
		// Note that a PNG can have multiple IDAT instances
		// (Although I don't believe they do in MNG-LC)
		public $IDAT = array();
		
		public $IEND = null;
	
		// @todo Might also need tRNS
		
		/** Attempts to construct an actual PNG file via stored chunk data */
		public function write($filename)
		{
			$fp = fopen($filename, 'wb');
			
			if (!$fp)
				throw new Exception('Could not open ' . $filename . ' for write');
			
			// Write PNG signature
			fwrite($fp, PNG_SIGNATURE); // @todo length?
			
			// Write header
			$this->write_chunk($fp, $this->IHDR['chunk']);
			
			// Write palette 
			$this->write_chunk($fp, $this->PLTE['chunk']);
			
			// Write notes if we have any
			if ($this->tEXt != null)
			{
				$this->write_chunk($fp, $this->tEXt);
			}
			
			// Write IDAT chunks (@todo Make sure the "more than one" case is properly handled)
			foreach ($this->IDAT as $IDAT)
			{
				$this->write_chunk($fp, $IDAT);
			}
			
			// Write the footer
			$this->write_chunk($fp, $this->IEND);
			
			fclose($fp);
		}
		
		
		/** Repacks a chunk to it's original binary state and writes to the file */
		public function write_chunk($fp, $chunk)
		{
			// Write size/id header
			fwrite($fp, pack('NN', $chunk['size'], $chunk['id']));
		
			// Write data stream if we have one
			if ($chunk['size'] > 0)
				fwrite($fp, $chunk['data'], $chunk['size']);
			
			// Write CRC footer
			fwrite($fp, pack('N', $chunk['crc']));
		}
		
	}
	
	class MNG_Image
	{
		private $size; // filesize in bytes
	
		private $MHDR = null; // MNG header array
		private $FRAM = null; // MNG framing mode array
			
		private $PLTE = null; // MNG palette array

		private $frames = array(); // Array of PNG_Object objects
		private $currentPNG = null; // Created when we encounter an IHDR, and moved into frames at IEND
	
		private $current_frame_key = '';
	
		public function load($filename)
		{
			$this->size = @filesize($filename);
			
			$fp = @fopen($filename, 'rb');
			if (!$fp)
			{
				throw new Exception('Could not open ' . $filename . ' for read');
			}
			
			if (!$this->read_signature($fp))
			{
				throw new Exception('File cannot be identified as MNG');
			}
		 	
			print 'Sig good<br>';
			
			$this->iterate_chunks($fp);
		}

		/**
		 * @return boolean
		 */
		private function read_signature($fp)
		{
			$sig = fread($fp, 8);
			return $sig == MNG_SIGNATURE;
		}

		private function read_chunk($fp)
		{
			print '<strong>read_chunk</strong><br>';
			
			// Read first section of the chunk
			$data = fread($fp, 8);
			
			print 'ID (raw): "' . substr($data, 4, 4) . '"<br>';
			
			// Unpack with uint32 for size, uint32 for ID
			$chunk = unpack('Nsize/Nid', $data);
			
			print 'ID (hex): 0x' . dechex($chunk['id']) . '<br>';
			print 'Size: ' . $chunk['size'] . '<br>';

			if ($chunk['size'] > 0)
			{
				// Read in the raw data of the specified size
				$chunk['data'] = fread($fp, $chunk['size']);
			}
			
			// Unpack suffix CRC uint32
			$data = fread($fp, 4);
			$tmp = unpack('Ncrc', $data);
			
			$chunk['crc'] = $tmp['crc'];
			
			print 'CRC: ' . dechex($chunk['crc']) . '<br><br>';
			
			return $chunk;
		}
		
		private function read_MHDR($chunk)
		{
			$fmt = 	'Nframe_width/' .
					'Nframe_height/' .
					'Nticks_per_second/' .
					'Nnominal_layer_count/' .
					'Nnominal_frame_count/' .
					'Nnominal_play_time/' .
					'Nsimplicity_profile/';
		
			$MHDR = unpack($fmt, $chunk['data']);
			
			print '<pre>MHDR:';
			print_r($MHDR);
			print '</pre>';
			
			return $MHDR;
		}
		
		private function read_PLTE($chunk)
		{
			$PLTE = array();
			$PLTE['chunk'] = $chunk;
			
			if ($chunk['size'] % 3 != 0)
			{
				throw new Exception('MNG_UINT_PLTE Not divisible by 3');
			}
			
			// Note that this was converted to a png_colorp object. 
			// @todo I assume it unpacks into RGB channels, so this 
			// needs to be translated 
			$PLTE['global_palette'] = $chunk['data'];
			
			// Number of colors in the palette (3 bytes each)
			$PLTE['global_palette_size'] = $chunk['size'] / 3;
			
			print '<pre>FRAM:';
			print_r($PLTE);
			print '</pre>';
			
			return $PLTE;
		}
		
		private function read_tRNS($chunk)
		{
			/*
				http://www.libpng.org/pub/png/spec/iso/index-object.html#11tRNS
				Depending on color type, there's various formats possible.
				To ease usage, support will focus on GIAM as the primary MNG generator. 
				
				I BELIEVE giam will produce the palette version all the time, since
				it's lazy. (color_type == 3) So that one will be handled

				color_type 3:
					the tRNS chunk contains a series of one-byte alpha values,
					corresponding to entries in the PLTE chunk.
			*/
			$tRNS = array();
			$tRNS['chunk'] = $chunk;
			
			$tRNS['trans_values'] = null;
			$tRNS['trans'] = $chunk['data'];
			$tRNS['num_trans'] = $chunk['size'];
			
			print '<pre>tRNS:';
			print_r($tRNS);
			print '</pre>';
			
			return $tRNS;
		}
		
		private function read_FRAM_old($fp)
		{
			$FRAM = array();
			$bytesRead = 0;
			
			$FRAM['framing_mode'] = fread($fp, 1);
			$bytesRead++;
			
			// @todo optimize this read method
			
			do // Skip over subframe_name (ends with a null byte)
			{
				$c = fread($fp, 1);
				$bytesRead++;
			} while ($c != 0);
			
			// Unpack the next 4 bytes of values
			// @todo: Do I need to unpack anything but change_interframe_delay?
			// Can't I just skip it?
			$data = fread($fp, 4);
			
			$fmt = 	'Cchange_interframe_delay/' .
					'Cchange_timeout_and_termination/' .
					'Cchange_layer_clipping_boundaries/' .
					'Cchange_sync_id_list';
					
			$tmp = unpack($fmt, $data);
			
			$FRAM = array_merge($FRAM, $tmp);
			
			$bytesRead += 4;
			
			// If there's an order to change, we need to read the new value
			if ($FRAM['change_interframe_delay'] != 0)
			{
				$data = fread($fp, 4);
				
				$fmt = 'Ninterframe_delay';
				$tmp = unpack($fmt, $data);
			
				$frame['interframe_delay'] = $tmp['interframe_delay'];
				
				$bytesRead += 4;
			}
			
			// Seek backward the amount of bytes read so we can skip the rest
			// of this FRAM chunk
			fseek($fp, $bytesRead * -1, SEEK_CUR);
			
			return $FRAM;
		}
		
		private function read_FRAM($chunk)
		{
			$FRAM = array();
			$FRAM['chunk'] = $chunk;
			
			$fmt = 	'Cframing_mode/' .
					'asubframe_name/' .
					'Cchange_interframe_delay/' .
					'Cchange_timeout_and_termination/' .
					'Cchange_layer_clipping_boundaries/' .
					'Cchange_sync_id_list/' . 
					'Ninterframe_delay'; // @todo can't guarantee interframe_delay will exist!
					
			$tmp = unpack($fmt, $chunk['data']);
			
			$FRAM = array_merge($FRAM, $tmp);
			
			print '<pre>FRAM:';
			print_r($FRAM);
			print '</pre>';
			
			return $FRAM;
		}
		
		private function read_IHDR($chunk)
		{
			$IHDR = array();
			$IHDR['chunk'] = $chunk;
			
			$fmt =  'Nwidth/' .
					'Nheight/' .
					'Cbit_depth/' .
					'Ccolor_type/' .
					'Ccompression_method/' .
					'Cfilter_method/' .
					'Cinterlace_method';
					
			$tmp = unpack($fmt, $chunk['data']);
			
			$IHDR = array_merge($IHDR, $tmp);
			
			print '<pre>IHDR:';
			print_r($IHDR);
			print '</pre>';
			
			return $IHDR;
		}
		
		private function iterate_chunks($fp)
		{
			$count = 0;
			$byteCount = 0;
			$totalBytes = $this->size;
			
			$doneWithHeader = false; // Set to true once we encounter a PNG
			$IHDRPosition = 0;
			
			do {
			
				$chunk = $this->read_chunk($fp);
				$byteCount += $chunk['size'] + 12; // len + id + ['size'] + crc
				
				switch ($chunk['id'])
				{
					/*	Header and global properties */
					case MNG_UINT_MHDR:
						
						//fseek($fp, ($chunk['size'] + 4) * -1, SEEK_CUR);
						$this->MHDR = $this->read_MHDR($chunk);
						
						break;
					
					/*  Set frame properties. Only appears if there's a change between
						two different frames. All frames that appear after this FRAM
						will use the same properties, until the next FRAM
					*/
					case MNG_UINT_FRAM:
						
						//fseek($fp, ($chunk['size'] + 4) * -1, SEEK_CUR);
						
						// Store the current frame properties for later reference
						$this->FRAM = $this->read_FRAM($chunk);
						
						// Seek forward again, skipping what we didn't read from FRAM
						//fseek($fp, $chunk['size'] + 4, SEEK_CUR);
						
						// @todo better way to handle this without seeking back/forward.
						// I imagine there's some subtraction that can be done somewhere
						break;
					
					/*	Set global palette on the first read of this chunk, ignoring
						all others that pop up (as they would be empty PNG palettes)
					*/
					case MNG_UINT_PLTE:
						
						if (!$doneWithHeader)
						{
							$this->PLTE = $this->read_PLTE($chunk);
						}
						break;
						
					/*	Modify global transparency */
					case MNG_UINT_tRNS:

						$this->tRNS = $this->read_tRNS($chunk);
						
						break;
						
					/* 	Marks the beginning of a PNG chunk series, and the end of our header */
					case MNG_UINT_IHDR:
						
						$doneWithHeader = true;
						$byteCount = $chunk['size'] + 12;
						$IHDRPosition = $byteCount;
						
						// Start of a new PNG object
						if ($this->currentPNG != null)
						{
							// Shouldn't happen. We should've copied it out at a preceeding IEND
							throw new Exception('MNG_UINT_IHDR before an IEND');
						}
						
						print '<i>Creating a new PNG object</i><br/>';
						$this->currentPNG = new PNG_Object();
						$this->currentPNG->IHDR = $this->read_IHDR($chunk);
						
						// Copy our current palette to the PNG
						if ($this->PLTE == null)
						{
							throw new Exception('No PLTE before IHDR');
						}
						
						$this->currentPNG->PLTE = $this->PLTE;
						
						break;
						
					case MNG_UINT_IDAT:
						
						if ($this->currentPNG == null)
							throw new Exception('MNG_UINT_IDAT with no working PNG');
							
						// Copy over to our current png
						$this->currentPNG->IDAT[] = $chunk;
						
						break;
						
					case MNG_UINT_tEXt:
						
						if ($this->currentPNG == null)
							throw new Exception('MNG_UINT_IEND with no working PNG');
						
						$this->currentPNG->tEXt = $chunk;
						
						// Change the global frame key based on the new key string
						// @todo I could probably use something faster than unpack here
						$tmp = unpack('a*', $chunk['data']);
						$this->current_frame_key = $tmp[1];
						
						break;
						
					/*	Marks the end of a PNG chunk series */
					case MNG_UINT_IEND:
						
						if ($this->currentPNG == null)
							throw new Exception('MNG_UINT_IEND with no working PNG');
							
						$this->currentPNG->IEND = $chunk;
		
						// IEND marks the end of a PNG image (a frame), so do any post-processing
						$this->finish_frame();
						
						// Seek to IHDR and read
						
						// If there were none encountered, then we are probably malformed
						/*if ($IHDRPosition == 0)
						{
							throw new Exception('Zero IHDR');
						}
					
						fseek($fp, $byteCount * -1, SEEK_CUR);
						
						// At this point, I assume we are right before IHDR (including length uint32)
						
						// Read the frame data 
						$index = count($frames);
						$tmp = $this->read_frame($fp);
						
						$tmp = array();
						$tmp['data'] = fread($fp, $byteCount);
						
						// spit it out
						$stream = fopen('frame' . $index . '.png', 'wb');
						fwrite($stream, "\x89PNG\r\n\x1A\n");
						fwrite($stream, $tmp['data']);
						fclose($stream);
						
						if (!$tmp)
						{
							throw new Exception('Could not read frame');
						}
						
						// Since certain MNGs can have a different TPS instead of 1000, factor it in
						// @todo divide by zero issue needs solving
						//$tmp['delay'] = 1000 / $this->mhdr['ticks_per_second'] * $this->fram['interframe_delay'];
						
						$frames[$index] = $tmp;
						
						$IHDRPosition = 0;
						*/
						
						break;
					
					/*	Unidentified chunk (or one we don't care about) */
					default:
						break;
				}
			
			} while ($chunk['id'] != MNG_UINT_MEND && $byteCount <= $totalBytes);
			
			
			// If a MEND wasn't found by the time we finished the file, we have a problem
			if ($chunk['id'] != MNG_UINT_MEND)
			{
				throw new Exception('No MNG_UINT_MEND found');
			}
			
			/*
				At this point, consider us done.
				
				Metadata for each image (including raw bytes) is in $this->frames
					and global MNG properties are within $this.
			*/
			
		}
		
		/** Called when we have fully populated $this->currentPNG with chunks from this MNG */
		private function finish_frame()
		{
			// Debugging to ensure we have everything
			print '<pre>PNG_Object:';
			print_r($this->currentPNG);
			print '</pre>';
			
			// For testing purposes, write the PNG itself to a file using the current chunks,
			// along with generating some additional metadata for examination purposes
			
			
			
			$filename = 'output/' . count($this->frames)
						. '-' . strval($this->FRAM['interframe_delay'])
						. '-' . $this->current_frame_key
						. '.png';
						
			$this->currentPNG->write($filename);
			
			
			/*
				Better logic would be to copy things like the key/delay/etc
				to the PNG object, and then after we finished reading the MNG,
				we would process frames and save to file or manipulate, etc 
			*/
			
			// Copy the current PNG into our frames array, since we're finished with it
			$this->frames[] = $this->currentPNG;
			$this->currentPNG = null;
		}
		
		private function read_frame($fp)
		{
			/* NO IDEA how to deal with this. 
			
				The original C code uses libpng for generating a png.
				In our case, we may need to gunzip the raw contents, then 
				potentially do a direct raw byte copy to a new GD image object,
				or something else entirely. Either way, I expect a slow process.
				
				From what I can tell:
					- Reads the "info" header
					- Reads in IHDR for the png (getting width/height/bit_depth/interlace_type/etc)
					- 
			*/
			
			/*
				Disregarding libpng and doing it manually, we find the following:
				
				1. We encounter the IHDR chunk and read in the following format:
					'Nwidth/' .
					'Nheight/' .
					'Cbit_depth/' .
					'Ccolor_type/' .
					'Ccompression_method/' .
					'Cfilter_method/' .
					'Cinterlace_method/';
					
					We find the following values for a test sample:
						w = 61
						h = 125
						bd = 4 - 4 bits per PALETTE pixel
						ct = 3 - each pixel is a PALETTE INDEX .: mandatory PLTE chunk
						cm = 0
						fm = 0
						im = 0
					
					So from the example data, we see that the palette is indeed required,
					and thus demands we write a mapping.
					
					http://www.libpng.org/pub/png/spec/1.2/PNG-Compression.html
			*/
			
			/*
				However, putting all of that aside, it seems that the minimum requirements
				for structuring a PNG are met (IHDR, PLTE, IDAT and IEND).
				It would then be potentially possible to construct a complete PNG object from 
				these parts (without decoding anything) and saving that composition to a file.
				
				It depends whether we want to apply the MNG frame modifications on the generated
				PNG, or later and just store the per-frame modifications as metadata, and let clientside
				deal with it.
				
				Personally, I'll go with the latter.
				
				So, we need to construct the following:
				- A PNG signature header (137 80 78 71 13 10 26 10)
				- IHDR, PLTE, IDAT and IEND chunks, each one with:
					- Length (uint32)
					- Type (uint32)
					- Data (Can be 0 length, such as IEND)
					- CRC (uint32)
				All of which can be directly copied from the MNG stream.
				
				In summary:
					- Create new stream
					- Write signature
					- Seek to beginning of the chunk (including length uint32)
					- Read until the end of the IEND chunk
					- End stream
					
				ALSO! Don't forget about that fucking tEXt chunk!
					Otherwise keyframing avatars just won't work!
					
				During iteration, we do see tEXt, so we could potentially load it in there 
				since it'll happen in the "current" frame set and we'd hit an IEND afterwards
			*/
			
			$data = fread($fp, 8 + 4 * 2 + 5);
			
			$fmt = 	'Nlength/' .
					'Nid/' .
					'Nwidth/' .
					'Nheight/' .
					'Cbit_depth/' .
					'Ccolor_type/' .
					'Ccompression_method/' .
					'Cfilter_method/' .
					'Cinterlace_method';
					
			$tmp = unpack($fmt, $data);
			
			// Undo
			fseek($fp, -(8 + 4 * 2 + 5), SEEK_CUR);
			
			print '<pre>IHDR:';
			print_r($tmp);
			print '</pre>';
			
			print 'This is where I would have read a frame.<br>';
			
			// if local palette is empty, we use the global palette.
			// (which is GIMP PNGs)
			
			/*
				The idea then becomes:
				- Check if this PNG has a filled PLTE.
					If it doesn't, delete that chunk, and replace it with the global MNG PLTE.
					This logic is crazy.
			*/
		}
		
		
		
		
		
		
		
	
	};
	
	$mng = new MNG_Image();
	$mng->load('tests/test_1.mng');
	
?>