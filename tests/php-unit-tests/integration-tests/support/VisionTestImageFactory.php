<?php
/**
 * Renders synthetic marker images for the vision integration tests.
 *
 * Images are generated at runtime from a short marker string (e.g. "ERR-4711")
 * rendered in a large bold font on a plain background. Nothing is checked in,
 * so the tests stay free of binary fixtures and of any real screenshot that
 * might carry personal data.
 *
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Itomig\iTop\AiBase\Test\Support;

class VisionTestImageFactory
{
	/** @var int Image width in pixels */
	public const WIDTH = 1024;

	/** @var int Image height in pixels */
	public const HEIGHT = 576;

	/** @var string[] Fonts tried in order, first readable one wins */
	private const FONT_CANDIDATES = [
		'/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
		'/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf',
		'/usr/share/fonts/truetype/noto/NotoSans-Bold.ttf',
	];

	/** @var array<string, string> Map of media type to GD output function */
	private const FORMATS = [
		'image/png' => 'imagepng',
		'image/jpeg' => 'imagejpeg',
		'image/gif' => 'imagegif',
		'image/webp' => 'imagewebp',
	];

	/**
	 * Renders an image containing the given marker and returns its raw bytes.
	 *
	 * @param string $sMarker Text to render, e.g. "ERR-4711"
	 * @param string $sMediaType One of image/png, image/jpeg, image/gif, image/webp
	 *
	 * @throws \InvalidArgumentException When GD is unavailable, the media type is unsupported or rendering fails
	 */
	public static function Render(string $sMarker, string $sMediaType = 'image/png'): string
	{
		if (!extension_loaded('gd')) {
			throw new \InvalidArgumentException('The GD extension is required to render vision test images.');
		}
		if (!isset(self::FORMATS[$sMediaType])) {
			throw new \InvalidArgumentException("Unsupported test image media type '{$sMediaType}'.");
		}

		$oImage = imagecreatetruecolor(self::WIDTH, self::HEIGHT);
		if ($oImage === false) {
			throw new \InvalidArgumentException('Could not allocate the test image.');
		}

		try {
			$iBackground = imagecolorallocate($oImage, 255, 255, 255);
			$iForeground = imagecolorallocate($oImage, 0, 0, 0);
			imagefilledrectangle($oImage, 0, 0, self::WIDTH, self::HEIGHT, $iBackground);
			// A border makes the marker unambiguous against a white chat background,
			// which helps models that crop or letterbox the image.
			imagerectangle($oImage, 8, 8, self::WIDTH - 9, self::HEIGHT - 9, $iForeground);

			self::DrawMarker($oImage, $sMarker, $iForeground);

			return self::Encode($oImage, self::FORMATS[$sMediaType]);
		} finally {
			imagedestroy($oImage);
		}
	}

	/**
	 * Returns the marker as a vision payload entry: raw base64 plus declared media type.
	 *
	 * @return array{data: string, media_type: string}
	 */
	public static function Payload(string $sMarker, string $sMediaType = 'image/png'): array
	{
		return [
			'data' => base64_encode(self::Render($sMarker, $sMediaType)),
			'media_type' => $sMediaType,
		];
	}

	/**
	 * Draws the marker centred on the image, scaling the font down until it fits.
	 *
	 * @param resource|\GdImage $oImage
	 * @param int               $iColor
	 */
	private static function DrawMarker($oImage, string $sMarker, int $iColor): void
	{
		$sFont = self::FindFont();

		if ($sFont === null) {
			// Bitmap fallback: small but legible enough for a smoke test.
			$iFont = 5;
			$iTextWidth = imagefontwidth($iFont) * strlen($sMarker);
			$iTextHeight = imagefontheight($iFont);
			imagestring(
				$oImage,
				$iFont,
				(int) ((self::WIDTH - $iTextWidth) / 2),
				(int) ((self::HEIGHT - $iTextHeight) / 2),
				$sMarker,
				$iColor
			);

			return;
		}

		for ($iSize = 140; $iSize >= 24; $iSize -= 4) {
			$aBox = imagettfbbox($iSize, 0, $sFont, $sMarker);
			$iTextWidth = abs($aBox[2] - $aBox[0]);
			$iTextHeight = abs($aBox[7] - $aBox[1]);
			if ($iTextWidth >= self::WIDTH - 120) {
				continue;
			}

			imagettftext(
				$oImage,
				$iSize,
				0,
				(int) ((self::WIDTH - $iTextWidth) / 2),
				(int) ((self::HEIGHT + $iTextHeight) / 2),
				$iColor,
				$sFont,
				$sMarker
			);

			return;
		}

		throw new \InvalidArgumentException("Marker '{$sMarker}' could not be rendered: too wide for the image.");
	}

	private static function FindFont(): ?string
	{
		foreach (self::FONT_CANDIDATES as $sFont) {
			if (is_readable($sFont)) {
				return $sFont;
			}
		}

		return null;
	}

	/**
	 * @param resource|\GdImage $oImage
	 */
	private static function Encode($oImage, string $sEncoder): string
	{
		ob_start();
		// Quality/compression arguments matter per format; defaults are fine for
		// a black-on-white marker image.
		$bOk = $sEncoder($oImage);
		$sBytes = (string) ob_get_clean();

		if (!$bOk || $sBytes === '') {
			throw new \InvalidArgumentException("Encoding the test image with {$sEncoder} failed.");
		}

		return $sBytes;
	}
}
