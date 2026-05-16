package com.facebook.react

import com.facebook.react.ReactPackage
import com.reactnativecommunity.asyncstorage.AsyncStoragePackage
import com.rnmaps.maps.MapsPackage
import com.swmansion.rnscreens.RNScreensPackage
import com.th3rdwave.safeareacontext.SafeAreaContextPackage
import com.oblador.vectoricons.VectorIconsPackage

class PackageList(private val reactNativeHost: ReactNativeHost) {
    val packages: ArrayList<ReactPackage>
        get() = arrayListOf(
            AsyncStoragePackage(),
            MapsPackage(),
            RNScreensPackage(),
            SafeAreaContextPackage(),
            VectorIconsPackage(),
        )
}
