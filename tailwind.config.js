/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    "./assets/**/*.js",
    "./templates/**/*.html.twig",
  ],
  safelist: [
    // Classes de base
    'text-white',
    'text-black',
    'text-transparent',
    'text-current',
    
    // Grays
    'text-slate-50', 'text-slate-100', 'text-slate-200', 'text-slate-300', 'text-slate-400', 'text-slate-500', 'text-slate-600', 'text-slate-700', 'text-slate-800', 'text-slate-900', 'text-slate-950',
    'text-gray-50', 'text-gray-100', 'text-gray-200', 'text-gray-300', 'text-gray-400', 'text-gray-500', 'text-gray-600', 'text-gray-700', 'text-gray-800', 'text-gray-900', 'text-gray-950',
    'text-zinc-50', 'text-zinc-100', 'text-zinc-200', 'text-zinc-300', 'text-zinc-400', 'text-zinc-500', 'text-zinc-600', 'text-zinc-700', 'text-zinc-800', 'text-zinc-900', 'text-zinc-950',
    'text-neutral-50', 'text-neutral-100', 'text-neutral-200', 'text-neutral-300', 'text-neutral-400', 'text-neutral-500', 'text-neutral-600', 'text-neutral-700', 'text-neutral-800', 'text-neutral-900', 'text-neutral-950',
    'text-stone-50', 'text-stone-100', 'text-stone-200', 'text-stone-300', 'text-stone-400', 'text-stone-500', 'text-stone-600', 'text-stone-700', 'text-stone-800', 'text-stone-900', 'text-stone-950',
    
    // Reds
    'text-red-50', 'text-red-100', 'text-red-200', 'text-red-300', 'text-red-400', 'text-red-500', 'text-red-600', 'text-red-700', 'text-red-800', 'text-red-900', 'text-red-950',
    
    // Oranges
    'text-orange-50', 'text-orange-100', 'text-orange-200', 'text-orange-300', 'text-orange-400', 'text-orange-500', 'text-orange-600', 'text-orange-700', 'text-orange-800', 'text-orange-900', 'text-orange-950',
    
    // Ambers
    'text-amber-50', 'text-amber-100', 'text-amber-200', 'text-amber-300', 'text-amber-400', 'text-amber-500', 'text-amber-600', 'text-amber-700', 'text-amber-800', 'text-amber-900', 'text-amber-950',
    
    // Yellows
    'text-yellow-50', 'text-yellow-100', 'text-yellow-200', 'text-yellow-300', 'text-yellow-400', 'text-yellow-500', 'text-yellow-600', 'text-yellow-700', 'text-yellow-800', 'text-yellow-900', 'text-yellow-950',
    
    // Limes
    'text-lime-50', 'text-lime-100', 'text-lime-200', 'text-lime-300', 'text-lime-400', 'text-lime-500', 'text-lime-600', 'text-lime-700', 'text-lime-800', 'text-lime-900', 'text-lime-950',
    
    // Greens
    'text-green-50', 'text-green-100', 'text-green-200', 'text-green-300', 'text-green-400', 'text-green-500', 'text-green-600', 'text-green-700', 'text-green-800', 'text-green-900', 'text-green-950',
    
    // Emeralds
    'text-emerald-50', 'text-emerald-100', 'text-emerald-200', 'text-emerald-300', 'text-emerald-400', 'text-emerald-500', 'text-emerald-600', 'text-emerald-700', 'text-emerald-800', 'text-emerald-900', 'text-emerald-950',
    
    // Teals
    'text-teal-50', 'text-teal-100', 'text-teal-200', 'text-teal-300', 'text-teal-400', 'text-teal-500', 'text-teal-600', 'text-teal-700', 'text-teal-800', 'text-teal-900', 'text-teal-950',
    
    // Cyans
    'text-cyan-50', 'text-cyan-100', 'text-cyan-200', 'text-cyan-300', 'text-cyan-400', 'text-cyan-500', 'text-cyan-600', 'text-cyan-700', 'text-cyan-800', 'text-cyan-900', 'text-cyan-950',
    
    // Skys
    'text-sky-50', 'text-sky-100', 'text-sky-200', 'text-sky-300', 'text-sky-400', 'text-sky-500', 'text-sky-600', 'text-sky-700', 'text-sky-800', 'text-sky-900', 'text-sky-950',
    
    // Blues
    'text-blue-50', 'text-blue-100', 'text-blue-200', 'text-blue-300', 'text-blue-400', 'text-blue-500', 'text-blue-600', 'text-blue-700', 'text-blue-800', 'text-blue-900', 'text-blue-950',
    
    // Indigos
    'text-indigo-50', 'text-indigo-100', 'text-indigo-200', 'text-indigo-300', 'text-indigo-400', 'text-indigo-500', 'text-indigo-600', 'text-indigo-700', 'text-indigo-800', 'text-indigo-900', 'text-indigo-950',
    
    // Violets
    'text-violet-50', 'text-violet-100', 'text-violet-200', 'text-violet-300', 'text-violet-400', 'text-violet-500', 'text-violet-600', 'text-violet-700', 'text-violet-800', 'text-violet-900', 'text-violet-950',
    
    // Purples
    'text-purple-50', 'text-purple-100', 'text-purple-200', 'text-purple-300', 'text-purple-400', 'text-purple-500', 'text-purple-600', 'text-purple-700', 'text-purple-800', 'text-purple-900', 'text-purple-950',
    
    // Fuchsias
    'text-fuchsia-50', 'text-fuchsia-100', 'text-fuchsia-200', 'text-fuchsia-300', 'text-fuchsia-400', 'text-fuchsia-500', 'text-fuchsia-600', 'text-fuchsia-700', 'text-fuchsia-800', 'text-fuchsia-900', 'text-fuchsia-950',
    
    // Pinks
    'text-pink-50', 'text-pink-100', 'text-pink-200', 'text-pink-300', 'text-pink-400', 'text-pink-500', 'text-pink-600', 'text-pink-700', 'text-pink-800', 'text-pink-900', 'text-pink-950',
    
    // Roses
    'text-rose-50', 'text-rose-100', 'text-rose-200', 'text-rose-300', 'text-rose-400', 'text-rose-500', 'text-rose-600', 'text-rose-700', 'text-rose-800', 'text-rose-900', 'text-rose-950',
    
    // Tailles de texte courantes
    'text-xs', 'text-sm', 'text-base', 'text-lg', 'text-xl', 'text-2xl', 'text-3xl', 'text-4xl', 'text-5xl', 'text-6xl',
    
    // Classes de background courantes aussi (au cas où)
    'bg-white', 'bg-black', 'bg-transparent',
    'bg-red-500', 'bg-orange-500', 'bg-amber-500', 'bg-yellow-500', 'bg-lime-500', 'bg-green-500',
    'bg-emerald-500', 'bg-teal-500', 'bg-cyan-500', 'bg-sky-500', 'bg-blue-500', 'bg-indigo-500',
    'bg-violet-500', 'bg-purple-500', 'bg-fuchsia-500', 'bg-pink-500', 'bg-rose-500',
    'bg-gray-500', 'bg-slate-500', 'bg-zinc-500', 'bg-neutral-500', 'bg-stone-500',

    // Classes slate pour le nouveau sélecteur
    'bg-slate-600', 'bg-slate-700', 'bg-slate-800', 'bg-slate-900',
    'bg-slate-700/50', 'bg-slate-800/50', 'bg-slate-800/60', 'bg-slate-900/50', 'bg-slate-900/95',
    'bg-slate-700/30', 'bg-slate-700/60', 'bg-slate-600/20', 'bg-blue-600/20',
    'border-slate-500', 'border-slate-600', 'border-slate-700',
    'border-slate-600/50', 'border-slate-600/30', 'border-slate-600/40',
    'hover:bg-slate-600', 'hover:bg-slate-700', 'hover:bg-slate-700/70', 'hover:bg-slate-700/80',
    'hover:bg-slate-800', 'hover:border-blue-500', 'hover:border-blue-400', 'hover:border-slate-400',
    'bg-emerald-600', 'bg-emerald-500', 'hover:bg-emerald-500', 'hover:bg-emerald-400',
    'bg-blue-900/20', 'bg-emerald-500/20', 'bg-amber-500/20', 'bg-blue-500/20',
    'bg-amber-600/20', 'bg-amber-600/40', 'bg-red-600/20', 'bg-red-600/40',
    'text-emerald-300', 'text-amber-300', 'text-amber-200', 'text-red-300', 'text-red-200',
    'text-blue-200', 'text-blue-300', 'text-blue-400', 'hover:text-blue-200', 'hover:text-blue-300',
    'text-red-100', 'text-red-200', 'border-red-300', 'border-red-400', 'border-emerald-500/40',
    'border-blue-500/40', 'border-amber-500/40', 'shadow-blue-500/20', 'shadow-emerald-500/25',
    'ring-2', 'ring-blue-500', 'rounded-xl', 'rounded-2xl', 'shadow-lg', 'shadow-2xl',
    'bg-gradient-to-br', 'bg-gradient-to-r', 'from-indigo-500', 'to-purple-600',
    'from-emerald-600', 'to-emerald-500', 'from-blue-500', 'to-indigo-500', 'from-blue-600', 'to-indigo-600',
    'from-purple-500', 'to-pink-500', 'from-red-600', 'to-red-500', 'from-red-500', 'to-red-400',
    'from-slate-700', 'to-slate-600', 'from-slate-800', 'to-slate-700',
    'animate-pulse', '-translate-y-1', '-translate-y-0.5', 'hover:-translate-y-1', 'hover:-translate-y-0.5',
    'transform', 'scale-110', 'group-hover:scale-110', 'group-hover:rotate-180',
    'backdrop-blur-xl', 'w-[28rem]', 'max-h-80', 'max-h-60',
    'z-[9998]', 'z-[9999]', 'z-[10000]', 'z-[10001]'
  ],
  theme: {
    extend: {},
  },
  plugins: [],
}
