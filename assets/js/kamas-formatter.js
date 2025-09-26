/**
 * Formatage des kamas selon le style Dofus
 */
export function formatKamas(amount) {
    if (amount === null || amount === undefined) {
        return '-'
    }

    const abs = Math.abs(amount)
    const sign = amount < 0 ? '-' : ''

    // Format Dofus: 700 000 000k = 700kk (millions de milliers)
    if (abs >= 1000000000) {
        const kk = Math.floor(abs / 1000000)
        return `${sign}${kk}kk`
    }

    // Format Dofus: 1 500 000k = 1m5k (millions + milliers)
    if (abs >= 1000000) {
        const millions = Math.floor(abs / 1000000)
        const remainder = abs % 1000000
        const milliers = Math.floor(remainder / 1000)

        if (milliers > 0) {
            return `${sign}${millions}m${milliers}k`
        } else {
            return `${sign}${millions}m`
        }
    }

    // Format standard: pas de "k" pour les milliers simples en affichage
    // Exemple: 1500 kamas = 1500 (pas 1.5k)
    if (abs >= 1000) {
        return `${sign}${abs.toLocaleString('fr-FR')}`
    }

    return `${sign}${abs}`
}

/**
 * Formatage avec HTML et couleurs pour l'affichage
 */
export function formatKamasWithHtml(amount) {
    if (amount === null || amount === undefined) {
        return '-'
    }

    const abs = Math.abs(amount)
    const sign = amount < 0 ? '-' : ''

    // Format Dofus: 700 000 000k = 700kk (millions de milliers)
    if (abs >= 1000000000) {
        const kk = Math.floor(abs / 1000000)
        return `${sign}${kk}<span class="text-orange-400">kk</span>`
    }

    // Format Dofus: 1 500 000k = 1m5k (millions + milliers)
    if (abs >= 1000000) {
        const millions = Math.floor(abs / 1000000)
        const remainder = abs % 1000000
        const milliers = Math.floor(remainder / 1000)

        if (milliers > 0) {
            return `${sign}${millions}<span class="text-orange-400">m</span>${milliers}<span class="text-orange-400">k</span>`
        } else {
            return `${sign}${millions}<span class="text-orange-400">m</span>`
        }
    }

    // Format standard: pas de "k" pour les milliers simples en affichage
    // Exemple: 1500 kamas = 1500 (pas 1.5k)
    if (abs >= 1000) {
        return `${sign}${abs.toLocaleString('fr-FR')}`
    }

    return `${sign}${abs}`
}