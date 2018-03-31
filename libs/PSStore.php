<?php

class PSStore
{
    private $URL = 'https://store.playstation.com/store/api/chihiro/00_09_000/titlecontainer/DE/de/999/';
    private $GameID;

    private $AgeLimit;
    private $RatingImage;

    private $GameName;
    private $LongDesc;
    private $Picture;
    private $ReleaseDate;
    private $ProviderName;

    private $StarRating;

    public function __construct($GameID)
    {
        $this->GameID = $GameID;
        $this->getData();
    }

    private function getData()
    {
        $ch = curl_init();
        $timeout = 5;
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_URL, $this->URL . $this->GameID . '_00');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US) AppleWebKit/525.13 (KHTML, like Gecko) Chrome/0.A.B.C Safari/525.13');
        $file_content = curl_exec($ch);
        curl_close($ch);

        $obj = json_decode($file_content);

        $this->AgeLimit = $obj->age_limit;
        $this->RatingImage = $obj->content_rating->url;
        $this->GameName = $obj->name;
        $this->LongDesc = $obj->long_desc;
        $this->Picture = $obj->images[0]->url;
        $this->ReleaseDate = $obj->release_date;
        $this->ProviderName = $obj->provider_name;
        $this->StarRating = $obj->star_rating;
    }

    /**
     * @return mixed
     */
    public function getGameID()
    {
        return $this->GameID;
    }

    /**
     * @return mixed
     */
    public function getAgeLimit()
    {
        return $this->AgeLimit;
    }

    /**
     * @return mixed
     */
    public function getRatingImage()
    {
        return $this->RatingImage;
    }

    /**
     * @return mixed
     */
    public function getGameName()
    {
        return $this->GameName;
    }

    /**
     * @return mixed
     */
    public function getLongDesc()
    {
        return $this->LongDesc;
    }

    /**
     * @return mixed
     */
    public function getPicture()
    {
        return $this->Picture;
    }

    /**
     * @return mixed
     */
    public function getReleaseDate()
    {
        return $this->ReleaseDate;
    }

    /**
     * @return mixed
     */
    public function getProviderName()
    {
        return $this->ProviderName;
    }

    /**
     * @return mixed
     */
    public function getStarRating()
    {
        return $this->StarRating;
    }
}
