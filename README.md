
# ⚶ Vesta Facts and events (Webtrees 2 Custom Module)

This [webtrees](https://www.webtrees.net/) custom module provides an extended 'Facts and Events' tab, with hooks for other custom modules.
The project’s website is [cissee.de](https://cissee.de).

This is a webtrees 2.x module - It cannot be used with webtrees 1.x. For its webtrees 1.x counterpart, see [here](https://github.com/ric2016/personal_facts_with_hooks).

## Contents

* [Features](#features)
* [Download](#download)
* [Installation](#installation)
* [License](#license)

### Features<a name="features"/>

Mainly intended as a base for other custom modules. Some features are available independently:

* Links to external maps (Google, Bing, OpenStreetMap) are configurable via module administration.

* Additional map services may be integrated on request. Currently we have:
    * [Mapire.eu](https://mapire.eu), providing a historical map of Europe in the XIX. century.

* The respective location data is obtained directly from GEDCOM, and may also be provided by other custom modules. 

* If you have collected non-GEDCOM location data via webtrees itself, activate the 'Vesta Webtrees Location Data Provider' custom module to make this data available.

<p align="center"><img src="providers.png" alt="Screenshot" align="center" width="67%"></p>

* Facts and events where a given individual is listed as an associate are also configurable. For these facts and events, the inverse associations and relationships are also displayed:

<p align="center"><img src="inverse.png" alt="Screenshot" align="center" width="67%"></p>

### Download<a name="download"/>

* Current version: 2.0.5.0.1
* Based on and tested with webtrees 2.0.5. Cannot be used with webtrees 1.x. May not work with earlier 2.x versions!
* Requires the ⚶ Vesta Common module ('vesta_common').
* Download the zipped module, including all related modules, [here](https://cissee.de/vesta.latest.zip).
* Support, suggestions, feature requests: <ric@richard-cissee.de>
* Issues also via <https://github.com/vesta-webtrees-2-custom-modules/vesta_personal_facts/issues>

### Installation<a name="installation"/>

* Unzip the files and copy them to the modules_v4 folder of your webtrees installation. All related modules are included in the zip file. It's safe to overwrite the respective directories if they already exist (they are bundled with other custom modules as well), as long as other custom models using these dependencies are also upgraded to their respective latest versions.
* Enable the extended 'Facts and Events' module via Control Panel -> Modules -> Module Administration -> ⚶ Vesta Facts and Events.
* Configure the visibility of the old and the extended 'Facts and Events' tab via Control Panel -> Modules -> Tabs (usually, you'll want to use only one of them. You may just disable the original 'Facts and Events' module altogether).

### License<a name="license"/>

* **vesta_personal_facts: a webtrees custom module**
* Copyright (C) 2019 - 2020 Richard Cissée
* Derived from **webtrees** - Copyright (C) 2010 to 2019 webtrees development team.
* French translations provided by Ghezibde.
* Dutch translations provided by TheDutchJewel.
* Slovak translations provided by Ladislav Rosival.

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program. If not, see <http://www.gnu.org/licenses/>.
