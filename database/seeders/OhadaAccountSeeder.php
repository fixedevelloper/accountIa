<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use App\Models\Account;

class OhadaAccountSeeder extends Seeder
{
    public function run()
    {
        $companyId = 1; // à adapter

        $accounts = [

            // 🔹 CLASSE 1 - Capitaux
            ['code' => '101000', 'name' => 'Capital social'],
            ['code' => '106000', 'name' => 'Réserves'],
            ['code' => '131000', 'name' => 'Résultat net'],

            // 🔹 CLASSE 2 - Immobilisations
            ['code' => '213000', 'name' => 'Bâtiments'],
            ['code' => '215000', 'name' => 'Matériel'],
            ['code' => '218000', 'name' => 'Autres immobilisations'],

            // 🔹 CLASSE 3 - Stocks
            ['code' => '311000', 'name' => 'Marchandises'],
            ['code' => '321000', 'name' => 'Matières premières'],

            // 🔹 CLASSE 4 - TIERS
            ['code' => '401000', 'name' => 'Fournisseurs'],
            ['code' => '411000', 'name' => 'Clients'],
            ['code' => '421000', 'name' => 'Personnel'],
            ['code' => '431000', 'name' => 'Sécurité sociale'],
            ['code' => '441000', 'name' => 'État'],

            // 🔹 TVA
            ['code' => '445660', 'name' => 'TVA déductible'],
            ['code' => '445710', 'name' => 'TVA collectée'],

            // 🔹 CLASSE 5 - Trésorerie
            ['code' => '521000', 'name' => 'Banque'],
            ['code' => '531000', 'name' => 'Caisse'],

            // 🔹 CLASSE 6 - CHARGES
            ['code' => '601000', 'name' => 'Achats marchandises'],
            ['code' => '602000', 'name' => 'Achats matières'],
            ['code' => '606000', 'name' => 'Fournitures'],
            ['code' => '613000', 'name' => 'Locations'],
            ['code' => '621000', 'name' => 'Personnel extérieur'],
            ['code' => '626000', 'name' => 'Télécommunication'],
            ['code' => '628000', 'name' => 'Divers'],
            ['code' => '631000', 'name' => 'Impôts et taxes'],
            ['code' => '661000', 'name' => 'Charges financières'],

            // 🔹 CLASSE 7 - PRODUITS
            ['code' => '701000', 'name' => 'Ventes marchandises'],
            ['code' => '706000', 'name' => 'Prestations de services'],
            ['code' => '707000', 'name' => 'Ventes diverses'],
            ['code' => '761000', 'name' => 'Produits financiers'],

        ];

        foreach ($accounts as $acc) {
            Account::updateOrCreate(
                [
                    'company_id' => $companyId,
                    'code' => $acc['code']
                ],
                [
                    'name' => $acc['name'],
                    'type' => substr($acc['code'], 0, 1) // classe
                ]
            );
        }
    }
}
