<?php

/*
    INSPIRED BY
    http://www.maptiler.org/google-maps-coordinates-tile-bounds-projection/
    Look there for more info.


    TMS Global Mercator Profile
    ---------------------------

    Functions necessary for generation of tiles in Spherical Mercator projection,
    EPSG:900913 (EPSG:gOOglE, Google Maps Global Mercator), EPSG:3785, OSGEO:41001.

    Such tiles are compatible with Google Maps, Microsoft Virtual Earth, Yahoo Maps,
    UK Ordnance Survey OpenSpace API, ...
    and you can overlay them on top of base maps of those web mapping applications.
    Pixel and tile coordinates are in TMS notation (origin [0,0] in bottom-left).

    What coordinate conversions do we need for TMS Global Mercator tiles::

    LatLon <-> Meters <-> Pixels <-> Tile

    WGS84 coordinates Spherical Mercator Pixels in pyramid Tiles in pyramid
    lat/lon XY in metres XY pixels Z zoom XYZ from TMS
    EPSG:4326 EPSG:900913
    .----. --------- -- TMS
    / \ <-> | | <-> /----/ <-> Google
    \ / | | /--------/ QuadTree
    ----- --------- /------------/
    KML, public WebMapService Web Clients TileMapService

    What is the coordinate extent of Earth in EPSG:900913?

        [-20037508.342789244, -20037508.342789244, 20037508.342789244, 20037508.342789244]
        Constant 20037508.342789244 comes from the circumference of the Earth in meters,
        which is 40 thousand kilometers, the coordinate origin is in the middle of extent.
        In fact you can calculate the constant as: 2 * math.pi * 6378137 / 2.0
        $ echo 180 85 | gdaltransform -s_srs EPSG:4326 -t_srs EPSG:900913
        Polar areas with abs(latitude) bigger then 85.05112878 are clipped off.

    What are zoom level constants (pixels/meter) for pyramid with EPSG:900913?

        whole region is on top of pyramid (zoom=0) covered by 256x256 pixels tile,
        every lower zoom level resolution is always divided by two
        initialResolution = 20037508.342789244 * 2 / 256 = 156543.03392804062

    What is the difference between TMS and Google Maps/QuadTree tile name convention?

        The tile raster itself is the same (equal extent, projection, pixel size),
        there is just different identification of the same raster tile.
        Tiles in TMS are counted from [0,0] in the bottom-left corner, id is XYZ.
        Google placed the origin [0,0] to the top-left corner, reference is XYZ.
        Microsoft is referencing tiles by a QuadTree name, defined on the website:
        http://msdn2.microsoft.com/en-us/library/bb259689.aspx

    The lat/lon coordinates are using WGS84 datum, yeh?

        Yes, all lat/lon we are mentioning should use WGS84 Geodetic Datum.
        Well, the web clients like Google Maps are projecting those coordinates by
        Spherical Mercator, so in fact lat/lon coordinates on sphere are treated as if
        the were on the WGS84 ellipsoid.
        From MSDN documentation:
        To simplify the calculations, we use the spherical form of projection, not
        the ellipsoidal form. Since the projection is used only for map display,
        and not for displaying numeric coordinates, we don't need the extra precision
        of an ellipsoidal projection. The spherical projection causes approximately
        0.33 percent scale distortion in the Y direction, which is not visually noticable.
*/

class GeoProjectionMercator
{
    protected $tileSize;
    protected $initialResolution;
    protected $originShift;


    /**
     * Initialize the TMS Global Mercator pyramid
     *
     * @param int $tileSize
     */
    public function __construct($tileSize = 256)
    {
        $this->tileSize = $tileSize;
        $this->initialResolution = 2 * M_PI * 6378137 / $tileSize;
        $this->originShift = 2 * M_PI * 6378137 / 2.0;
    }


    /**
     * Converts given lat/lon in WGS84 Datum to XY in Spherical Mercator EPSG:900913"
     *
     * @param float $lat
     * @param float $lon
     * @return array array('xm' => float, 'xy' => float)
     */
    public function LatLonToMeters($lat, $lon)
    {
        $mx = $lon * $this->originShift / 180.0;
        $my = log( tan((90 + $lat) * M_PI / 360.0 )) / (M_PI / 180.0);

        $my = $my * $this->originShift / 180.0;

        return array('xm' => $mx, 'ym' => $my);
    }

    /**
     * Converts XY point from Spherical Mercator EPSG:900913 to lat/lon in WGS84 Datum
     *
     * @param float $mx
     * @param float $my
     * @return array array('lat' => float, 'lon' => float)
     */
    public function MetersToLatLon($mx, $my)
    {
        $lon = ($mx / $this->originShift) * 180.0;
        $lat = ($my / $this->originShift) * 180.0;

        $lat = 180 / M_PI * (2 * atan( exp( $lat * M_PI / 180.0)) - M_PI / 2.0);

        return array('lat' => $lat, 'lon' => $lon);
    }

    /**
     * Converts EPSG:900913 to pyramid pixel coordinates in given zoom level
     *
     * @param float $xm
     * @param float $ym
     * @param int $zoom
     * @return array array('xp' => float, 'yp' => float)
     */
    public function MetersToPixels($xm, $ym, $zoom)
    {
        $res = $this->Resolution( $zoom );
        $px = ($xm + $this->originShift) / $res;
        $py = ($ym + $this->originShift) / $res;

        return array('xp' => $px, 'yp' => $py);
    }

    /**
     * Converts pixel coordinates in given zoom level of pyramid to EPSG:900913
     *
     * @param float $px
     * @param float $py
     * @param int $zoom
     * @return array array('x' => float, 'y' => float)
     */
    public function PixelsToMeters($px, $py, $zoom)
    {
        $res = $this->Resolution($zoom);
        $mx = $px * $res - $this->originShift;
        $my = $py * $res - $this->originShift;

        return array('x' => $mx, 'y' => $my);
    }

    /**
     * Returns bounds of the given tile in EPSG:900913 coordinates
     *
     * @param float $tx
     * @param float $ty
     * @param int $zoom
     * @return array array('min' => float, 'max' => float)
     */
    public function TileBounds($tx, $ty, $zoom)
    {
        $min = $this->PixelsToMeters( $tx * $this->tileSize, $ty * $this->tileSize, $zoom );
        $max = $this->PixelsToMeters( ($tx + 1) * $this->tileSize, ($ty + 1) * $this->tileSize, $zoom );

        return array( 'min' => $min, 'max' => $max );
    }

    /**
     * Returns bounds of the given tile in latutude/longitude using WGS84 datum
     *
     * @param float $tx
     * @param float $ty
     * @param int $zoom
     * @return array array('min' => float, 'max' => float)
     */
    public function TileLatLonBounds($tx, $ty, $zoom)
    {
        $bounds = $this->TileBounds( $tx, $ty, $zoom );
        $min = $this->MetersToLatLon($bounds['min']['x'], $bounds['min']['y']);
        $max = $this->MetersToLatLon($bounds['max']['x'], $bounds['max']['y']);

        return array( 'min' => $min, 'max' => $max );
    }

    /**
     * Converts TMS Y-tile coordinate to Google Tile coordinates and vice versa (TMS => Google)
     *
     * @param int $ty
     * @param int $zoom
     * @return int
     */
    public function GoogleTileYcoord($ty, $zoom)
    {
        // coordinate origin is moved from bottom-left to top-left corner of the extent
        return (pow(2, $zoom) - 1) - $ty;
    }


    /**
     * Convert latitude,longitude and zoom into tile number
     *
     * @param float $lat
     * @param float $lon
     * @param int $zoom
     * @return array array('x' => int, 'y' => int)
     */
    public function LatLonToTile($lat, $lon, $zoom)
    {
        $ar = $this->LatLonToMeters($lat, $lon);
        $coord = $this->MetersToPixels($ar['xm'], $ar['ym'], $zoom);
        if ($coord['yp'] < $this->tileSize) {
            $coord['yp'] = 0;
        }
        $tile_x = intval($coord['xp'] / $this->tileSize);
        $tile_y = intval($coord['yp'] / $this->tileSize);
        $tile_y = $this->GoogleTileYcoord($tile_y, $zoom);

        return array('x'=>$tile_x, 'y'=>$tile_y);
    }

    /**
     * Resolution (meters/pixel) for given zoom level (measured at Equator)
     *
     * @param int $zoom Number 0-21
     * @return float
     */
    protected function Resolution($zoom)
    {
        return $this->initialResolution / pow(2, $zoom);
    }
}