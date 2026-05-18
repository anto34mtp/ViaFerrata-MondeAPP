import React from 'react';
import {createBottomTabNavigator} from '@react-navigation/bottom-tabs';
import {createNativeStackNavigator} from '@react-navigation/native-stack';
import Icon from 'react-native-vector-icons/MaterialIcons';
import {useAuth} from '../context/AuthContext';
import {useLang} from '../context/LangContext';

import HomeScreen from '../screens/HomeScreen';
import CatalogScreen from '../screens/CatalogScreen';
import ViaDetailScreen from '../screens/ViaDetailScreen';
import MapScreen from '../screens/MapScreen';
import LoginScreen from '../screens/LoginScreen';
import RegisterScreen from '../screens/RegisterScreen';
import DashboardScreen from '../screens/DashboardScreen';
import FavoritesScreen from '../screens/FavoritesScreen';
import LogbookScreen from '../screens/LogbookScreen';
import RoadTripsScreen from '../screens/RoadTripsScreen';
import RoadTripDetailScreen from '../screens/RoadTripDetailScreen';
import RoadTripCreateScreen from '../screens/RoadTripCreateScreen';
import SubmitViaScreen from '../screens/SubmitViaScreen';
import SettingsScreen from '../screens/SettingsScreen';

export type RootStackParamList = {
  Main: undefined;
  ViaDetail: {slug: string};
  Login: undefined;
  Register: undefined;
  Favorites: undefined;
  Logbook: undefined;
  RoadTrips: undefined;
  RoadTripDetail: {id: number};
  RoadTripCreate: undefined;
  SubmitVia: undefined;
};

export type TabParamList = {
  Home: undefined;
  Catalog: undefined;
  Map: undefined;
  Dashboard: undefined;
  More: undefined;
};

const Stack = createNativeStackNavigator<RootStackParamList>();
const Tab = createBottomTabNavigator<TabParamList>();

const PRIMARY = '#2E7D32';

function HomeTabs() {
  const {t} = useLang();
  const {user} = useAuth();

  return (
    <Tab.Navigator
      screenOptions={({route}) => ({
        tabBarActiveTintColor: PRIMARY,
        tabBarInactiveTintColor: '#888',
        tabBarStyle: {paddingBottom: 4},
        headerStyle: {backgroundColor: PRIMARY},
        headerTintColor: '#fff',
        headerTitleStyle: {fontWeight: 'bold'},
        tabBarIcon: ({color, size}) => {
          const icons: Record<string, string> = {
            Home: 'home',
            Catalog: 'list',
            Map: 'map',
            Dashboard: 'person',
            More: 'menu',
          };
          return <Icon name={icons[route.name] ?? 'circle'} size={size} color={color} />;
        },
      })}>
      <Tab.Screen name="Home" component={HomeScreen} options={{title: t('nav.home')}} />
      <Tab.Screen name="Catalog" component={CatalogScreen} options={{title: t('nav.catalog')}} />
      <Tab.Screen name="Map" component={MapScreen} options={{title: t('nav.map')}} />
      <Tab.Screen
        name="Dashboard"
        component={user ? DashboardScreen : LoginScreen}
        options={{title: user ? t('nav.dashboard') : t('nav.login')}}
      />
      <Tab.Screen name="More" component={SettingsScreen} options={{title: t('nav.more')}} />
    </Tab.Navigator>
  );
}

export default function AppNavigator() {
  return (
    <Stack.Navigator
      screenOptions={{
        headerStyle: {backgroundColor: PRIMARY},
        headerTintColor: '#fff',
        headerTitleStyle: {fontWeight: 'bold'},
      }}>
      <Stack.Screen name="Main" component={HomeTabs} options={{headerShown: false}} />
      <Stack.Screen name="ViaDetail" component={ViaDetailScreen} options={{title: 'Détail'}} />
      <Stack.Screen name="Login" component={LoginScreen} options={{title: 'Connexion'}} />
      <Stack.Screen name="Register" component={RegisterScreen} options={{title: 'Inscription'}} />
      <Stack.Screen name="Favorites" component={FavoritesScreen} options={{title: 'Favoris'}} />
      <Stack.Screen name="Logbook" component={LogbookScreen} options={{title: 'Carnet de sorties'}} />
      <Stack.Screen name="RoadTrips" component={RoadTripsScreen} options={{title: 'Road Trips'}} />
      <Stack.Screen name="RoadTripDetail" component={RoadTripDetailScreen} options={{title: 'Road Trip'}} />
      <Stack.Screen name="RoadTripCreate" component={RoadTripCreateScreen} options={{title: 'Nouveau Road Trip'}} />
      <Stack.Screen name="SubmitVia" component={SubmitViaScreen} options={{title: 'Proposer une via'}} />
    </Stack.Navigator>
  );
}
