<?php
// ============================================================
// Armel IA : assistant agricole (LLM si clé dans .env, sinon base locale)
// ============================================================

function armel_system_prompt(array $user, ?array $reading): string
{
    $ctx = 'Type d\'exploitation : ' . type_label($user['type_exploitation']) . '.';
    if ($reading) {
        $ctx .= " Mesures actuelles : température {$reading['temperature']}°C, humidité du sol {$reading['soil_humidity']}%, qualité de l'air {$reading['air_quality']}/100.";
    }
    return "Tu es Armel, l'assistant agricole d'AgriElv Smart AI, spécialisé dans l'agriculture et l'élevage au Cameroun. "
        . "Réponds toujours en français simple, en 6 phrases maximum, avec des conseils concrets et réalistes pour de petits exploitants "
        . "(cacao, manioc, plantain, maïs, arachide, tomate, volailles, porcs, bovins...). Tiens compte des saisons camerounaises et de la chaleur. "
        . "N'invente jamais de dosage de produit chimique ou de médicament. Pour une maladie grave ou une mortalité animale anormale, "
        . "recommande de contacter un vétérinaire ou la délégation locale MINEPIA/MINADER. Contexte de l'utilisateur : $ctx";
}

/** Appel API Anthropic (clé lue dans .env uniquement). Retourne null si indisponible. */
function armel_llm(array $history, string $system): ?string
{
    $key = env('ANTHROPIC_API_KEY');
    if (!$key || !function_exists('curl_init')) return null;
    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_HTTPHEADER => ['content-type: application/json', 'x-api-key: ' . $key, 'anthropic-version: 2023-06-01'],
        CURLOPT_POSTFIELDS => json_encode([
            'model' => env('ARMEL_MODEL', 'claude-sonnet-5-5'),
            'max_tokens' => 600,
            'system' => $system,
            'messages' => $history,
        ], JSON_UNESCAPED_UNICODE),
    ]);
    $out = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($out === false || $code !== 200) return null;
    $j = json_decode($out, true);
    $t = $j['content'][0]['text'] ?? null;
    return is_string($t) && trim($t) !== '' ? trim($t) : null;
}

/** Base de connaissances locale (mode hors clé API / panne réseau) */
function armel_fallback(string $msg, ?array $reading): string
{
    $m = mb_strtolower($msg);
    $kb = [
        [['cacao', 'cacaoyer', 'cabosse'], "Pour le cacao : gardez de l'ombre (bananiers, arbres d'ombrage), récoltez les cabosses mûres chaque 2 à 3 semaines et retirez les cabosses pourries pour limiter la pourriture brune. Fermentez 5 à 6 jours puis séchez au soleil sur claies. Évitez de sécher sur le sol."],
        [['manioc'], "Pour le manioc : plantez des boutures saines de 20-25 cm sur butte au début de la saison des pluies. Désherbez à 1 et 2 mois. La récolte se fait entre 9 et 12 mois selon la variété. Rotation avec arachide ou maïs pour garder le sol fertile."],
        [['maïs', 'mais'], "Pour le maïs : semez dès les premières bonnes pluies, 2 graines par poquet espacées de 25 cm sur lignes de 75 cm. Désherbez tôt et surveillez la chenille légionnaire (feuilles trouées) : ramassez-les le matin et demandez conseil à votre délégation MINADER."],
        [['tomate'], "Pour la tomate : arrosez au pied tôt le matin, paillez le sol pour garder l'humidité et tuteurez les plants. Évitez de mouiller les feuilles pour limiter le mildiou. Enlevez les plants malades sans les laisser sur la parcelle."],
        [['poule', 'poulet', 'volaille', 'pondeuse'], "Pour les volailles : offrez de l'eau fraîche en permanence, surtout par forte chaleur. Gardez le poulailler aéré et sec, nettoyez la litière souvent et isolez tout oiseau malade. Vaccinez contre Newcastle selon le calendrier d'un vétérinaire."],
        [['porc', 'porcin', 'cochon'], "Pour les porcs : gardez la porcherie propre, ombragée et bien ventilée. Donnez de l'eau à volonté. En cas de fièvre, de taches rouges ou de mortalité, isolez les bêtes et appelez un vétérinaire sans attendre (risque de peste porcine)."],
        [['vache', 'bovin', 'bétail', 'betail', 'chèvre', 'mouton'], "Pour le bétail : assurez eau propre et ombre chaque jour, déparasitez régulièrement et suivez le calendrier de vaccination avec un vétérinaire. En saison sèche, prévoyez du fourrage de réserve (foin, résidus de culture)."],
        [['chaleur', 'température', 'temperature', 'chaud', 'surchauffe'], "Quand il fait trop chaud : arrosez tôt le matin ou en fin de journée, paillez le sol, ajoutez de l'ombre (filets, feuilles de palmier) et donnez plus d'eau aux animaux. Évitez de les déplacer aux heures les plus chaudes."],
        [['sec', 'sécheresse', 'secheresse', 'humidité', 'humidite', 'arros'], "Sol trop sec : arrosez lentement et en profondeur plutôt que souvent et en surface, de préférence tôt le matin. Le paillage (herbe sèche, feuilles) réduit l'évaporation. Vérifiez à 5 cm de profondeur avec le doigt avant d'arroser."],
        [['maladie', 'tache', 'jaunit', 'jaune', 'champignon', 'insecte', 'parasite'], "Pour identifier le problème : notez quelle partie est touchée (feuille, tige, fruit), depuis quand et si cela se propage. Retirez et brûlez les parties atteintes, évitez les excès d'eau et contactez la délégation MINADER avec une photo pour un diagnostic fiable."],
    ];
    foreach ($kb as [$words, $answer]) {
        foreach ($words as $w) if (str_contains($m, $w)) return $answer;
    }
    if ($reading) {
        $t = $reading['temperature']; $h = $reading['soil_humidity'];
        return "Voici l'état de votre exploitation : {$t}°C et sol à {$h}% d'humidité. Posez-moi une question sur une culture (cacao, manioc, maïs, tomate) ou un élevage (volaille, porc, bovin) et je vous conseille.";
    }
    return "Je suis Armel. Posez-moi une question sur une culture (cacao, manioc, maïs, tomate) ou un élevage (volaille, porc, bovin), la chaleur ou l'arrosage.";
}
