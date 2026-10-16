// Alpine llega con Livewire, que lo arranca en DOMContentLoaded: este módulo (diferido) corre antes y alcanza `alpine:init`.

// pdf.js y su worker se descargan con import() la primera vez que se abre una vista previa: no pesan en este bundle.
let pdfjs = null
const cargarPdfjs = () =>
    (pdfjs ??= import('pdfjs-dist')
        .then((lib) => {
            // Un solo worker compartido (Vite lo emite como chunk .js).
            lib.GlobalWorkerOptions.workerPort ??= new Worker(new URL('pdfjs-dist/build/pdf.worker.min.mjs', import.meta.url), { type: 'module' })
            return lib
        })
        .catch((e) => {
            pdfjs = null
            throw e
        }))

const ANCHO_PX = 420 // ancho de render; el CSS escala el canvas
const ALTO_MAX_PX = 1600 // tope de alto para páginas muy largas
const HOJAS_DETRAS = 4 // igual al @for de x-ui.pdf-preview

document.addEventListener('alpine:init', () => {
    // x-ui.pdf-preview: portada (página 1) + hasta 4 hojas detrás que se abren en abanico.
    window.Alpine.data('vistaPdf', () => {
        // Fuera del proxy reactivo de Alpine: pdf.js usa campos privados que se rompen al envolverlos.
        let documento = null
        let carga = null

        return {
            cargando: false,
            listo: false,
            error: false,
            abanico: false,
            total: 0,
            hojas: 0,

            get paginas() {
                return this.total === 1 ? '1 página' : `${this.total} páginas`
            },

            abrir() {
                this.$refs.dialogo.showModal()
                carga ??= this.cargar()
            },

            abrirCompleto() {
                window.open(this.$refs.enlace.href, '_blank', 'noopener,noreferrer')
            },

            async cargar() {
                this.cargando = true
                this.error = false
                try {
                    const lib = await cargarPdfjs()
                    // Solo el href que pintó el servidor (misma ruta de descarga, protegida por `view`).
                    documento = await lib.getDocument({ url: this.$refs.enlace.href, isEvalSupported: false }).promise
                    this.total = documento.numPages
                    this.hojas = Math.min(documento.numPages - 1, HOJAS_DETRAS)
                    await this.pintar(1, this.$refs.portada)
                    this.listo = true
                    for (let i = 1; i <= this.hojas; i++) {
                        await this.pintar(i + 1, this.$refs[`hoja${i}`])
                    }
                    // Los canvas conservan sus píxeles: se libera el documento y su memoria.
                    await documento.destroy()
                    documento = null
                } catch (e) {
                    console.error('[vistaPdf]', e)
                    if (!this.listo) {
                        this.error = true
                        carga = null // se reintenta al volver a abrir
                    }
                } finally {
                    this.cargando = false
                }
            },

            async pintar(numero, canvas) {
                const pagina = await documento.getPage(numero)
                const base = pagina.getViewport({ scale: 1 })
                const viewport = pagina.getViewport({ scale: Math.min(ANCHO_PX / base.width, ALTO_MAX_PX / base.height) * Math.min(window.devicePixelRatio || 1, 2) })
                canvas.width = Math.floor(viewport.width)
                canvas.height = Math.floor(viewport.height)
                if (numero === 1) {
                    this.$refs.pila.style.setProperty('--proporcion', `${base.width} / ${base.height}`)
                }
                await pagina.render({ canvas, viewport }).promise
            },
        }
    })
})
