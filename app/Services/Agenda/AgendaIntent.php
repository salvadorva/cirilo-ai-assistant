<?php

namespace App\Services\Agenda;

/**
 * F2-03: decide si un mensaje pide crear algo en la agenda, antes de gastar en el extractor.
 *
 * - Verbos inequívocos («agéndame», «recuérdame», «anótame») bastan.
 * - Verbos genéricos («puedes crear», «programa un») solo cuentan si hay algo de agenda (cita,
 *   llamada, hora, día) y nada de programación («función en PHP», «script»).
 * - Una negación («no quiero agendar», «no me agendes») nunca crea.
 */
class AgendaIntent
{
    private const STRONG = '/\b(ag[eé]nd(ame|ala|alo|anos)|ap[uú]nt(ame|ala|alo)|an[oó]t(ame|ala|alo)|recu[eé]rd(ame|anos)|recordarme|'
        .'ponme (un )?recordatorio|pon un recordatorio|(pon|a[nñ]ade|agrega)(lo|la)? (en|a) mi agenda|agenda (una|un|el|la|para)\b|'
        .'(quiero|quisiera|necesito|tengo que|me gustar[ií]a|ay[uú]dame a) agendar|quiero que (me )?(agendes|lo agendes|la agendes)|'
        .'quisiera que (agendes|programes|me avises)|necesito que agendes|crea(r)? (un|el) evento|crea(r)? una (cita|reuni[oó]n)|registra (un|el) evento|registra la reuni[oó]n)/u';

    private const WEAK = '/\b(puedes (crear|programar|agendar)|programa(r)? (un|una|el)|crea (un|una)|quiero que (crees|programes)|'
        .'gu[aá]rd(ala|alo)|pon(la|lo) en|progr[aá]mame)\b/u';

    private const AGENDA_NOUN = '/\b(evento|cita|reuni[oó]n|llamada|recordatorio|junta|clase de|consulta|cumplea[nñ]os|agenda|calendario|'
        .'ma[nñ]ana|hoy|pasado|lunes|martes|mi[eé]rcoles|jueves|viernes|s[aá]bado|domingo|semana|a las \d|\d{1,2}(:\d{2})?\s*(am|pm|hrs?|h)\b)/u';

    private const CODE = '/\b(funci[oó]n|c[oó]digo|script|php|python|javascript|java|sql|html|css|api|variable|m[eé]todo|clase en|'
        .'archivo|tabla|base de datos|algoritmo|programa en|aplicaci[oó]n|app|bot)\b/u';

    // Solo cuando el verbo de agenda va justo después («no quiero agendar», «no me agendes»); así
    // «recuérdame que no se me olvide poner…» sigue siendo una petición.
    private const NEGATION = '/\b(no|nunca|ya no|tampoco)\s+((quiero|quisiera|necesito|me|te|lo|la|les?)\s+)?(agend|program|anot|apunt|cre[ae]|record|pon|guard)/u';

    private const ABANDON = '/\b(olv[ií]d(alo|ala|a eso)|ya no( quiero| lo| la)?|mejor no|d[eé]j(alo|ala)|no importa|cancela(lo|la)?( eso)?|no lo agendes|no la agendes)\b/u';

    public static function wantsToCreate(string $prompt): bool
    {
        $text = mb_strtolower($prompt);
        if (preg_match(self::NEGATION, $text)) {
            return false;
        }
        if (preg_match(self::STRONG, $text)) {
            return true;
        }

        return preg_match(self::WEAK, $text) && preg_match(self::AGENDA_NOUN, $text) && ! preg_match(self::CODE, $text);
    }

    /** El usuario desiste de lo que se estaba agendando. */
    public static function abandons(string $prompt): bool
    {
        return (bool) preg_match(self::ABANDON, mb_strtolower($prompt));
    }
}
