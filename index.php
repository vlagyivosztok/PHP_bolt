<?php

// ------------------------------------------
// 1. Általános Függvények (CRUD Műveletek)
// ------------------------------------------

/**
 * Beolvassa a JSON fájl tartalmát és PHP tömbként visszaadja.
 * @return array A bevásárlólista elemek tömbje.
 */
function read_list() {
    $filename = 'lista.json';
    if (!file_exists($filename) || filesize($filename) === 0) {
        return []; // Üres tömb, ha a fájl nem létezik vagy üres
    }
    // file_get_contents: beolvassa a fájl tartalmát sztringként
    $json_data = file_get_contents($filename);
    // json_decode: JSON sztringet PHP tömbbé konvertál
    return json_decode($json_data, true); 
}

/**
 * Felülírja a JSON fájlt a megadott PHP tömbbel.
 * @param array $list A listát tartalmazó PHP tömb.
 * @return bool Sikeres volt-e az írás.
 */
function write_list(array $list) {
    $filename = 'lista.json';
    // json_encode: PHP tömböt JSON sztringgé konvertál
    $json_data = json_encode($list, JSON_PRETTY_PRINT);
    // file_put_contents: beírja az adatot a fájlba
    return file_put_contents($filename, $json_data);
}

// ------------------------------------------
// 2. Műveletek Kezelése (POST kérések)
// ------------------------------------------

// Csak akkor dolgozzuk fel a kérést, ha az POST metódusú
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $list = read_list();
    $action = $_POST['action'] ?? '';
    
    // ÚJ ELEM HOZZÁADÁSA
    if ($action === 'add' && !empty($_POST['text'])) {
        $new_text = trim($_POST['text']);
        if ($new_text !== '') {
            // Új ID generálása: A legnagyobb ID + 1. 
            $new_id = empty($list) ? 1 : max(array_column($list, 'id')) + 1;
            
            $list[] = [
                'id' => $new_id,
                'text' => $new_text,
                'done' => false
            ];
            write_list($list);
        }
    }
    
    // ELEM MÓDOSÍTÁSA/TÖRLÉSE (Ezt a következő fázisban építjük ki)
$item_id = (int)($_POST['id'] ?? 0);
    
    // TÖRLÉS
    if ($action === 'delete' && $item_id > 0) {
        // Kiszűrjük azt az elemet, aminek az ID-je megegyezik a törlendővel
        $list = array_filter($list, function($item) use ($item_id) {
            return $item['id'] !== $item_id;
        });
        // Újraindexeljük a tömböt, ha kell (bár a JSON ID-t használ)
        $list = array_values($list); 
        write_list($list);
    }
    
    // MÓDOSÍTÁS (Szerkesztés gomb)
    elseif ($action === 'update' && $item_id > 0 && !empty($_POST['text'])) {
        $new_text = trim($_POST['text']);
        if ($new_text !== '') {
            foreach ($list as &$item) { // Fontos a & az elem hivatkozására
                if ($item['id'] === $item_id) {
                    $item['text'] = $new_text;
                    break;
                }
            }
            unset($item); // Töröljük a hivatkozást
            write_list($list);
        }
    }
    
    // KÉSZ/VISSZA VÁLTÁSA (Toggle gomb)
    elseif ($action === 'toggle' && $item_id > 0) {
        foreach ($list as &$item) {
            if ($item['id'] === $item_id) {
                // Megfordítja a 'done' állapotot (true -> false, false -> true)
                $item['done'] = !$item['done']; 
                break;
            }
        }
        unset($item);
        write_list($list);
    }

    // TÖMEGES TÖRLÉS (Kipipált elemek)
    elseif ($action === 'delete_done') {
        // Kiszűrjük azokat az elemeket, amelyeknél a 'done' állapota HAMIS
        $list = array_filter($list, function($item) {
            return $item['done'] === false;
        });
        // Újraindexeljük a tömböt
        $list = array_values($list); 
        write_list($list);
    }
    
    // Megakadályozzuk az űrlap újraküldését frissítéskor (Post/Redirect/Get pattern)
    header('Location: index.php');
    exit;
}

// ------------------------------------------
// 3. Adatok Beolvasása a Megjelenítéshez
// ------------------------------------------

$list = read_list();

// ------------------------------------------
// 4. HTML Generálás (Mobile-First)
// ------------------------------------------
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> 
    <title>Bevásárlólista</title>
<style>
    /* ALAP STÍLUSOK (Mobile-First) - KOMPAKTABB VERZIÓ */
    body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, Ubuntu, Cantarell, "Fira Sans", "Droid Sans", "Helvetica Neue", sans-serif; margin: 0; padding: 10px; background-color: #f4f4f9; }
    .container { max-width: 600px; margin: 0 auto; background-color: #fff; padding: 10px; border-radius: 6px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
    h1 { margin-top: 0; margin-bottom: 15px; font-size: 1.8em; }
    
    /* Új elem hozzáadása űrlap - KOMPAKTABB */
    .add-form { display: flex; gap: 8px; margin-bottom: 15px; }
    .add-form input[type="text"] { flex-grow: 1; padding: 8px; border: 1px solid #ccc; border-radius: 4px; font-size: 14px; }
    .add-form button { padding: 8px 12px; background-color: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; }
    .add-form button:hover { background-color: #0056b3; }
    
    /* Lista elemek stílusa - KOMPAKTABB */
    .list-item-form { 
        display: flex; 
        flex-wrap: wrap;
        align-items: center; 
        border-bottom: 1px solid #eee;
        padding: 8px 0;
    }
    
    /* Input mező - ALACSONYABB */
    .list-item-input { 
        flex-grow: 1; 
        padding: 8px; 
        border: 1px solid #ddd; 
        border-radius: 4px; 
        font-size: 16px;
        min-width: 0;
    }

    /* Kész (Done) elemek stílusa */
    .done .list-item-input {
        text-decoration: line-through;
        color: #888;
        background-color: #f9f9f9;
    }
    
    /* Műveleti gombok tárolója (Mentés/Törlés) */
    .action-buttons {
        display: none; /* Alapból rejtett */
        gap: 5px;
        margin-left: 8px;
    }

    /* Ha a mező vagy a gombok aktívak, megjelenítjük a gombokat */
    .list-item-input:focus ~ .action-buttons,
    .action-buttons:focus-within,
    .action-buttons:hover,
    .action-buttons:active {
        display: flex;
    }

    /* Gombok stílusa */
    .action-buttons button {
        padding: 8px 12px;
        font-size: 14px;
        border-radius: 4px;
        cursor: pointer;
        border: none;
        color: white;
    }

    .edit-btn { background-color: #28a745; }
    .delete-btn { background-color: #dc3545; }
    .edit-btn:hover { background-color: #1e7e34; }
    .delete-btn:hover { background-color: #c82333; }

    /* Pipa gomb (Toggle) - Fixen a sor végén */
    .toggle-btn { 
        background: none; 
        border: none; 
        font-size: 24px; 
        cursor: pointer; 
        color: #333;
        padding: 0 0 0 10px;
        line-height: 1;
        display: flex;
        align-items: center;
    }
    .toggle-btn:hover { color: #000; background: none; }

    /* MEDIA QUERY (Mobil nézet) */
    @media (max-width: 500px) {
        /* Mobilon a gombok kerüljenek új sorba, teljes szélességben */
        .action-buttons {
            width: 100%;
            margin-left: 0;
            margin-top: 8px;
            justify-content: space-between;
            order: 3; /* A gombok kerüljenek a vizuális sorrend végére (új sorba) */
        }
        
        .action-buttons button {
            flex-grow: 1;
        }

        .toggle-btn {
            order: 2; /* A pipa kerüljön közvetlenül a szövegmező (order: 0) után */
        }
    }

    /* Tömeges törlés gomb - KOMPAKTABB */
    .delete-done-form button {
        padding: 10px;
        font-size: 14px;
        margin-top: 15px;
    }
</style>
</head>
<body>
    <div class="container">
        <h1>🛒 Bevásárlólista</h1>

        <form class="add-form" method="POST" action="index.php">
            <input type="text" name="text" placeholder="Új elem..." required>
            <button type="submit" name="action" value="add">Mentés</button>
        </form>

        <?php if (empty($list)): ?>
            <p>A lista üres. Kezdj el hozzáadni tételeket!</p>
        <?php else: ?>
            <?php foreach ($list as $item): ?>
                <?php 
                    // Stílus osztály hozzáadása, ha kész (done)
                    $class = $item['done'] ? 'list-item-form done' : 'list-item-form'; 
                ?>
                <form class="<?php echo $class; ?>" method="POST" action="index.php">
                    <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                    


                    <input 
                        type="text" 
                        name="text" 
                        value="<?php echo htmlspecialchars($item['text']); ?>" 
                        class="list-item-input" 
                        required
                    >

                    <!-- Műveleti gombok (csak fókusz esetén látszanak) -->
                    <div class="action-buttons">
                        <button type="submit" name="action" value="update" class="edit-btn">
                            Mentés
                        </button>
                        <button type="submit" name="action" value="delete" class="delete-btn" formnovalidate>
                            Törlés
                        </button>
                    </div>

                    <!-- Pipa gomb (mindig látszik, sor végén) -->
                    <button 
                        type="submit" 
                        name="action" 
                        value="toggle" 
                        class="toggle-btn"
                        title="<?php echo $item['done'] ? 'Vissza' : 'Kész'; ?>"
                    >
                        <?php echo $item['done'] ? '&#9745;' : '&#9744;'; ?>
                    </button>
                </form>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <?php 
            // Csak akkor mutatjuk a gombot, ha van legalább egy "kész" tétel a listán
            $has_done = array_reduce($list, function($carry, $item) {
                return $carry || $item['done'];
            }, false);
            
            if ($has_done):
        ?>
            <form method="POST" action="index.php" style="margin-top: 25px;">
                <button type="submit" name="action" value="delete_done" style="width: 100%; padding: 12px; background-color: #f7a01a; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 16px;">
                    🧹 Törölje a kipipált (kész) elemeket
                </button>
            </form>
        <?php endif; ?>
</body>
</html>