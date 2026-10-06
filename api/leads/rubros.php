<?php
declare(strict_types=1);

require_once __DIR__ . '/mb.php';

// Rubro en español → categorías de Overture Maps.
// Cada categoría también matchea sus subcategorías (ej: "restaurant" incluye "pizza_restaurant").
const RUBROS = [
    'peluquería' => ['beauty_salon', 'hair_salon', 'barber'],
    'barbería' => ['barber'],
    'estética' => ['beauty_salon', 'spa', 'nail_salon', 'laser_hair_removal', 'waxing', 'hair_removal', 'makeup_artist'],
    'manicura' => ['nail_salon'],
    'spa y masajes' => ['spa', 'massage_therapy'],
    'tatuajes' => ['tattoo_and_piercing'],
    'gimnasio' => ['gym', 'sport_or_fitness_facility', 'fitness_trainer', 'martial_arts_club', 'gymnastics_center'],
    'pilates y yoga' => ['pilates_studio', 'yoga_studio'],
    'danza' => ['dance_studio'],
    'restaurante' => ['restaurant'],
    'pizzería' => ['pizza_restaurant'],
    'parrillada' => ['steakhouse', 'barbecue_restaurant', 'bar_and_grill_restaurant'],
    'hamburguesería' => ['burger_restaurant'],
    'sushi' => ['sushi_restaurant'],
    'rotisería' => ['food_delivery_service', 'sandwich_shop', 'chicken_restaurant', 'fast_food_restaurant', 'food_truck_stand'],
    'cafetería' => ['coffee_shop', 'cafe', 'tea_room'],
    'bar' => ['bar', 'pub', 'beer_bar', 'lounge', 'gastropub', 'tapas_bar'],
    'panadería' => ['bakery'],
    'confitería' => ['bakery', 'dessert_shop', 'cupcake_shop', 'chocolatier', 'candy_store'],
    'heladería' => ['ice_cream_shop'],
    'catering' => ['caterer'],
    'almacén' => ['grocery_store', 'convenience_store'],
    'supermercado' => ['grocery_store', 'warehouse_club_store', 'superstore'],
    'carnicería' => ['butcher_shop'],
    'verdulería' => ['produce_store'],
    'fiambrería' => ['delicatessen', 'cheese_shop'],
    'bebidas' => ['liquor_store', 'food_beverage_distributor'],
    'farmacia' => ['pharmacy'],
    'ferretería' => ['hardware_store', 'home_improvement_store'],
    'barraca' => ['building_supply_store', 'lumber_store'],
    'mueblería' => ['furniture_store', 'mattress_store'],
    'bazar' => ['home_goods_store', 'linen_store'],
    'vivero' => ['nursery_and_gardening_store', 'gardener'],
    'florería' => ['flowers_and_gifts_store'],
    'ropa' => ['clothing_store', 'fashion_boutique', 'fashion_accessories_store', 'lingerie_store', 'sportswear_store'],
    'zapatería' => ['shoe_store'],
    'óptica' => ['eyewear_store', 'vision_or_eye_care_clinic'],
    'joyería' => ['jewelry_store'],
    'librería' => ['bookstore', 'office_supply_store'],
    'juguetería' => ['toy_store'],
    'celulares' => ['mobile_phone_store'],
    'informática' => ['computer_store', 'it_service_and_computer_repair', 'electronics_store'],
    'electrodomésticos' => ['appliance_store', 'appliance_repair_service'],
    'bicicletería' => ['bike_store', 'bike_repair_maintenance'],
    'taller mecánico' => ['automotive_repair', 'automotive_service', 'motorcycle_repair'],
    'chapa y pintura' => ['auto_body_shop'],
    'gomería' => ['tire_dealer_and_repair'],
    'lavadero de autos' => ['car_wash', 'auto_detailing'],
    'repuestos' => ['auto_parts_store'],
    'automotora' => ['auto_dealer', 'used_auto_dealer', 'motorcycle_dealer'],
    'lavandería' => ['laundromat'],
    'veterinaria' => ['veterinarian', 'veterinary_care', 'pet_groomer'],
    'pet shop' => ['pet_store', 'pet_groomer'],
    'dentista' => ['dental_clinic', 'general_dentistry', 'orthodontics'],
    'clínica médica' => ['doctors_office', 'outpatient_care_facility', 'laboratory_testing'],
    'psicólogo' => ['psychology', 'psychotherapy', 'counseling'],
    'fisioterapia' => ['physical_therapy'],
    'nutricionista' => ['nutrition_service'],
    'abogado' => ['attorney_or_law_firm', 'legal_service'],
    'escribano' => ['notary_public'],
    'contador' => ['accountant'],
    'inmobiliaria' => ['real_estate_service', 'real_estate_agent', 'property_management'],
    'arquitecto' => ['architectural_designer', 'interior_design'],
    'construcción' => ['building_or_construction_service', 'contractor', 'home_developer'],
    'electricista' => ['electrician'],
    'sanitario' => ['plumbing'],
    'cerrajería' => ['key_and_locksmith'],
    'carpintería' => ['carpenter'],
    'herrería' => ['metal_fabricator'],
    'vidriería' => ['glass_and_mirror_sales_service', 'windows_installation'],
    'aire acondicionado' => ['hvac_service'],
    'limpieza' => ['home_cleaning', 'janitorial_service'],
    'eventos' => ['party_and_event_planning', 'caterer', 'party_supply_store'],
    'fotógrafo' => ['event_photography_service', 'photographer'],
    'imprenta' => ['printing_service', 't_shirt_printing_service'],
    'hotel' => ['hotel', 'hostel', 'bed_and_breakfast', 'resort'],
    'alquiler temporario' => ['holiday_rental_home', 'cabin', 'campground'],
    'agencia de viajes' => ['travel_service', 'tour_operator', 'travel_company'],
    'academia' => ['language_school', 'tutoring_service', 'art_school', 'music_school', 'specialty_school'],
    'escuela de idiomas' => ['language_school'],
    'escuela de manejo' => ['driving_school'],
    'colegio privado' => ['private_school', 'preschool'],
    'fletes' => ['freight_and_cargo_service', 'movers'],
    'seguros' => ['insurance_agency'],
    'residencial' => ['retirement_home'],
    'funeraria' => ['funeral_service'],
];

function rubro_normalizar(string $s): string
{
    $s = mb_strtolower(trim($s));
    return strtr($s, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);
}

/** Categorías de Overture para un rubro escrito por el usuario (vacío si no lo conoce). */
function rubro_categorias(string $rubro): array
{
    $q = rubro_normalizar($rubro);
    if ($q === '') {
        return [];
    }
    $cats = [];
    foreach (RUBROS as $nombre => $lista) {
        $n = rubro_normalizar($nombre);
        if ($n === $q || str_starts_with($n, $q) || str_contains($q, $n)) {
            array_push($cats, ...$lista);
        }
    }
    return array_values(array_unique($cats));
}
