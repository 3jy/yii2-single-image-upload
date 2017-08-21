<?php

namespace alexeevdv\image;

use Imagine\Image\Palette\Color;
use Imagine\Image\Box;
use Imagine\Image\ImageInterface;
use Imagine\Image\Palette\RGB;
use Imagine\Image\Point;
use Yii;
use yii\base\Behavior;
use yii\base\InvalidParamException;
use yii\helpers\Url;
use yii\imagine\Image;

/**
 * Class SingleImageThumbnailBehavior
 * @package alexeevdv\image
 */
class SingleImageThumbnailBehavior extends Behavior
{
    /**
     * @var array
     */
    public $thumbnails = [];

    /**
     * @var string
     */
    public $sourcePath = '@frontend/web/uploads';

    /**
     * @var string
     */
    public $destinationPath;

    /**
     * @var string
     */
    public $baseUrl = null;

    /**
     * @inheritdoc
     */
    public function init()
    {
        if ($this->destinationPath === null) {
            $this->destinationPath = $this->sourcePath;
        }
        parent::init();
    }

    /**
     * @param string $attribute
     * @param string $type
     * @return string
     */
    public function getThumbnail($attribute, $type)
    {
        if (!isset($this->thumbnails[$type])) {
            throw new InvalidParamException('Invalid thumbnail type: ' . $type);
        }
        $thumbnail = $this->thumbnails[$type];

        if (file_exists($this->generateThumbnailPath($attribute, $type))) {
            return $this->generateUrl($attribute, $type);
        }

        $image = Image::getImagine()->open($this->generateSourcePath($attribute));
        if (!$this->checkImageSize($image, $thumbnail['width'], $thumbnail['height'])) {
            $image = $this->enlargeImage($image, $thumbnail['width'], $thumbnail['height']);
        }

        $mode = isset($thumbnail['mode']) ? $thumbnail['mode'] : ImageInterface::THUMBNAIL_OUTBOUND;
        $image = $image->thumbnail(new Box($thumbnail['width'], $thumbnail['height']), $mode);

        if (isset($thumbnail['mode']) && $thumbnail['mode'] === ImageInterface::THUMBNAIL_INSET) {
            // TODO: pass background color and opacity
            $image = $this->padImage($image, $thumbnail['width'], $thumbnail['height']);
        }

        $image->save($this->generateThumbnailPath($attribute, $type));

        return $this->generateUrl($attribute, $type);
    }

    /**
     * @param ImageInterface $image
     * @param int $width
     * @param int $height
     * @return bool
     */
    protected function checkImageSize(ImageInterface $image, $width, $height)
    {
        return $image->getSize()->getWidth() >= $width && $image->getSize()->getWidth() >= $height;
    }

    /**
     * @param ImageInterface $image
     * @param int $width
     * @param int $height
     * @return ImageInterface
     */
    protected function enlargeImage(ImageInterface $image, $width, $height)
    {
        // Calculate ratio of desired maximum sizes and original sizes.
        $widthRatio = $width / $image->getSize()->getWidth();
        $heightRatio = $height / $image->getSize()->getHeight();

        // Ratio used for calculating new image dimensions.
        $ratio = max($widthRatio, $heightRatio);

        // Calculate new image dimensions.
        $newWidth  = (int)$image->getSize()->getWidth()  * $ratio;
        $newHeight = (int)$image->getSize()->getHeight() * $ratio;

        return $image->resize(new Box(
                $newWidth,
                $newHeight
            )
        );
    }

    protected function padImage(ImageInterface $img, $width, $height, $bg_color = '#fff', $bg_alpha = 100)
    {
        $size = $img->getSize();
        $x = $y = 0;
        if ($width > $size->getWidth()) {
            $x =  round(($width - $size->getWidth()) / 2);
        } elseif ($height > $size->getHeight()) {
            $y = round(($height - $size->getHeight()) / 2);
        }

        $palette = new RGB;
        $color = $palette->color($bg_color, $bg_alpha);
        $image = Image::getImagine()->create(new Box($width, $height), $color);

        $pasteto = new Point($x, $y);
        $image->paste($img, $pasteto);

        return $image;
    }

    /**
     * @param string $attribute
     * @param string $type
     * @return string
     */
    protected function generateThumbnailPath($attribute, $type)
    {
        return rtrim(Yii::getAlias($this->destinationPath), '/') . '/' . $type . '-' . $this->owner->$attribute;
    }

    /**
     * @param string $attribute
     * @return string
     */
    protected function generateSourcePath($attribute)
    {
        return rtrim(Yii::getAlias($this->sourcePath), '/') . '/' . $this->owner->$attribute;
    }

    /**
     * @param string $attribute
     * @param string $type
     * @return string
     */
    protected function generateUrl($attribute, $type)
    {
        if ($this->baseUrl) {
            return Url::to(rtrim(Yii::getAlias($this->baseUrl), '/') . '/' . $type . '-' . $this->owner->$attribute);
        }

        $fullPath =  rtrim($this->destinationPath, '/') . '/' . $type . '-' . $this->owner->$attribute;
        $baseUrl = str_replace('@frontend/web', '', $fullPath);
        return Url::to($baseUrl);
    }
}
